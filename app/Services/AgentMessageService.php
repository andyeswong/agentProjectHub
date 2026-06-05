<?php

namespace App\Services;

use App\Exceptions\AgentCommsException;
use App\Models\AgentLink;
use App\Models\AgentMessage;
use App\Models\ApiKey;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Directed 1:1 messages that flow inside an OPEN link, plus the inbox
 * (with optional long-poll) agents drain on their turn.
 */
class AgentMessageService
{
    public function __construct(
        private AgentLinkService $links,
        private AgentCommsService $comms,
        private ActivityEventService $events,
    ) {}

    public function send(ApiKey $from, string $linkId, array $data): AgentMessage
    {
        // Validate the link is OPEN and insert the message in ONE transaction,
        // locking the link row so a concurrent idle-close (GC) cannot slip in
        // between the check and the insert. If the link expired/closed in the
        // meantime the row lock surfaces it and we return 409 link_not_open.
        return DB::transaction(function () use ($from, $linkId, $data) {
            $link = AgentLink::where('id', $linkId)
                ->where(fn ($q) => $q->where('initiator_id', $from->id)->orWhere('target_id', $from->id))
                ->lockForUpdate()
                ->first();

            if (! $link) {
                throw AgentCommsException::make('link_not_found',
                    'Link not found or you are not a party to it.', 404);
            }
            if (! $link->isOpen()) {
                throw AgentCommsException::make('link_not_open',
                    "Link is '{$link->status}'. Messages require an open link.", 409);
            }

            if (! empty($data['idempotency_key'])) {
                $existing = AgentMessage::where('from_id', $from->id)
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->first();
                if ($existing) {
                    return $existing->load('from');
                }
            }

            $message = AgentMessage::create([
                'org_id'          => $link->org_id,
                'link_id'         => $link->id,
                'from_id'         => $from->id,
                'type'            => $data['type'] ?? 'message',
                'correlation_id'  => $data['correlation_id'] ?? null,
                'body'            => $data['body'] ?? null,
                'meta'            => $data['meta'] ?? null,
                'refs'            => $data['refs'] ?? null,
                'priority'        => $data['priority'] ?? 'normal',
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            $link->update(['last_activity_at' => now()]);

            $recipient = $link->otherParty($from->id);

            // Wake the recipient's inbox long-poll the instant this commits.
            // Inside the transaction, Postgres queues the NOTIFY and delivers it
            // on COMMIT — atomic with the insert above. No-op on other drivers.
            if ($recipient && DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('SELECT pg_notify(?, ?)', [
                    self::notifyChannel($recipient),
                    json_encode(['type' => 'message', 'link_id' => $link->id, 'priority' => $message->priority]),
                ]);
            }

            $this->events->record('agent.message_sent', 'agent_message', $message->id, $from, [
                'link_id'  => $link->id,
                'to'       => $recipient,
                'priority' => $message->priority,
            ]);

            return $message->load('from');
        });
    }

    /**
     * Unread directed messages + pending handshakes for this agent.
     * If $wait > 0, long-poll until something arrives or the window elapses.
     *
     * @return array{messages: Collection, pending_links: Collection}
     */
    /**
     * RPC: send a request message and block until the matching response arrives
     * (same link, same correlation_id, type=response, from the other party) or
     * the timeout elapses. Reuses the LISTEN/NOTIFY fabric, so the wake is
     * instant when the peer replies via agent_send(type=response, correlation_id).
     *
     * @return array{correlation_id: string, request: AgentMessage, response: ?AgentMessage, timed_out: bool}
     */
    public function rpc(ApiKey $from, string $linkId, array $data, int $timeout): array
    {
        $timeout = max(1, min($timeout, (int) config('agent_comms.inbox_max_wait')));
        $cid     = $data['correlation_id'] ?? (string) Str::uuid();

        $data['correlation_id'] = $cid;
        $data['type']           = 'request';

        // send() validates the open link transactionally and notifies the peer.
        $request  = $this->send($from, $linkId, $data);
        $response = $this->waitForResponse($from, $linkId, $cid, $timeout);

        return [
            'correlation_id' => $cid,
            'request'        => $request,
            'response'       => $response,
            'timed_out'      => $response === null,
        ];
    }

    /** Block for the RPC response carrying $cid, marking it read once consumed. */
    private function waitForResponse(ApiKey $agent, string $linkId, string $cid, int $timeout): ?AgentMessage
    {
        $check = fn (): ?AgentMessage => AgentMessage::where('link_id', $linkId)
            ->where('correlation_id', $cid)
            ->where('type', 'response')
            ->where('from_id', '!=', $agent->id)
            ->with('from')
            ->first();

        $consume = function (?AgentMessage $m): ?AgentMessage {
            if ($m && $m->read_at === null) {
                $m->forceFill(['read_at' => now()])->save();
            }
            return $m;
        };

        if ($found = $check()) {
            return $consume($found);
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            $pdo     = DB::connection()->getPdo();
            $channel = self::notifyChannel($agent->id);
            $quoted  = '"' . str_replace('"', '""', $channel) . '"';
            $pdo->exec('LISTEN ' . $quoted);
            try {
                $deadline = microtime(true) + $timeout;
                while (true) {
                    if ($found = $check()) {
                        return $consume($found);
                    }
                    $remainingMs = (int) (($deadline - microtime(true)) * 1000);
                    if ($remainingMs <= 0) {
                        return null;
                    }
                    $pdo->pgsqlGetNotify(\PDO::FETCH_ASSOC, min($remainingMs, 5000));
                }
            } finally {
                $pdo->exec('UNLISTEN ' . $quoted);
            }
        }

        // Non-pgsql fallback (tests).
        $deadline = microtime(true) + $timeout;
        $pollMs   = max(200, (int) config('agent_comms.inbox_poll_ms'));
        while (microtime(true) < $deadline) {
            usleep($pollMs * 1000);
            if ($found = $check()) {
                return $consume($found);
            }
        }
        return null;
    }

    /**
     * Paginated message history for a link this agent is a party to — for
     * rebuilding context after a session restart. Returns newest-first; pass
     * ?before=<ISO> to page backwards.
     */
    public function history(ApiKey $agent, string $linkId, ?string $before, int $limit): Collection
    {
        // Authorize: must be a party to the link (throws if not).
        $this->links->listFor($agent); // refreshes GC
        $link = AgentLink::where('id', $linkId)
            ->where(fn ($q) => $q->where('initiator_id', $agent->id)->orWhere('target_id', $agent->id))
            ->first();

        if (! $link) {
            throw AgentCommsException::make('link_not_found', 'Link not found or you are not a party to it.', 404);
        }

        return AgentMessage::where('link_id', $linkId)
            // Cast to Carbon so Eloquent binds it in the driver's datetime format
            // (a raw ISO string would mis-compare lexically on some drivers).
            ->when($before, fn ($q) => $q->where('created_at', '<', \Illuminate\Support\Carbon::parse($before)))
            ->with('from')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function inbox(ApiKey $agent, int $wait = 0): array
    {
        $wait = max(0, min($wait, config('agent_comms.inbox_max_wait')));

        // Polling the inbox doubles as the availability heartbeat: an agent
        // that keeps a poll loop running stays "online"; one that stops is
        // considered stale by the directory.
        $this->comms->heartbeat($agent);

        // Immediate, non-blocking check first — also the wait=0 fast path.
        $found = $this->drainInbox($agent);
        if ($wait === 0 || $found['messages']->isNotEmpty() || $found['pending_links']->isNotEmpty()) {
            return $found;
        }

        // Postgres: block on LISTEN/NOTIFY so we return the instant a message
        // or handshake arrives (sub-millisecond wake), instead of busy-polling.
        if (DB::connection()->getDriverName() === 'pgsql') {
            return $this->waitViaNotify($agent, $wait);
        }

        // Other drivers (sqlite tests): fall back to a short busy-poll loop.
        $deadline = microtime(true) + $wait;
        $pollMs   = max(200, config('agent_comms.inbox_poll_ms'));
        while (microtime(true) < $deadline) {
            usleep($pollMs * 1000);
            $found = $this->drainInbox($agent);
            if ($found['messages']->isNotEmpty() || $found['pending_links']->isNotEmpty()) {
                return $found;
            }
        }

        return ['messages' => collect(), 'pending_links' => collect()];
    }

    /** Per-agent Postgres NOTIFY channel an inbox waiter LISTENs on. */
    public static function notifyChannel(string $agentId): string
    {
        return 'inbox:' . $agentId;
    }

    /**
     * Block up to $wait seconds on the agent's NOTIFY channel, re-draining the
     * inbox whenever woken. LISTEN is issued BEFORE the (re)check so a message
     * arriving mid-check is not missed — its notification is queued and picked
     * up by the next pgsqlGetNotify.
     */
    private function waitViaNotify(ApiKey $agent, int $wait): array
    {
        $pdo     = DB::connection()->getPdo();
        $channel = self::notifyChannel($agent->id);
        $quoted  = '"' . str_replace('"', '""', $channel) . '"';

        $pdo->exec('LISTEN ' . $quoted);
        try {
            $deadline = microtime(true) + $wait;
            while (true) {
                $found = $this->drainInbox($agent);
                if ($found['messages']->isNotEmpty() || $found['pending_links']->isNotEmpty()) {
                    return $found;
                }

                $remainingMs = (int) (($deadline - microtime(true)) * 1000);
                if ($remainingMs <= 0) {
                    return ['messages' => collect(), 'pending_links' => collect()];
                }

                // Blocks until a NOTIFY on this channel or the timeout elapses.
                $pdo->pgsqlGetNotify(\PDO::FETCH_ASSOC, min($remainingMs, 5000));
            }
        } finally {
            $pdo->exec('UNLISTEN ' . $quoted);
        }
    }

    /** One non-blocking read of unread messages + pending handshakes. */
    private function drainInbox(ApiKey $agent): array
    {
        return [
            'messages'      => $this->unreadMessages($agent),
            'pending_links' => $this->links->pending($agent),
        ];
    }

    public function ack(ApiKey $agent, array $messageIds): int
    {
        $linkIds = $this->agentLinkIds($agent);

        return AgentMessage::whereIn('id', $messageIds)
            ->whereIn('link_id', $linkIds)
            ->where('from_id', '!=', $agent->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    private function unreadMessages(ApiKey $agent): Collection
    {
        $openLinkIds = AgentLink::where('status', 'open')
            ->where(fn ($q) => $q->where('initiator_id', $agent->id)->orWhere('target_id', $agent->id))
            ->pluck('id');

        return AgentMessage::whereIn('link_id', $openLinkIds)
            ->where('from_id', '!=', $agent->id)
            ->whereNull('read_at')
            ->with('from')
            ->orderBy('created_at')
            ->get();
    }

    private function agentLinkIds(ApiKey $agent): Collection
    {
        return AgentLink::where(fn ($q) => $q->where('initiator_id', $agent->id)->orWhere('target_id', $agent->id))
            ->pluck('id');
    }
}
