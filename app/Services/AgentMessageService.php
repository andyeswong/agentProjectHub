<?php

namespace App\Services;

use App\Models\AgentLink;
use App\Models\AgentMessage;
use App\Models\ApiKey;
use Illuminate\Support\Collection;

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
        $link = $this->links->openLinkForSend($from, $linkId);

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

        $this->events->record('agent.message_sent', 'agent_message', $message->id, $from, [
            'link_id'  => $link->id,
            'to'       => $link->otherParty($from->id),
            'priority' => $message->priority,
        ]);

        return $message->load('from');
    }

    /**
     * Unread directed messages + pending handshakes for this agent.
     * If $wait > 0, long-poll until something arrives or the window elapses.
     *
     * @return array{messages: Collection, pending_links: Collection}
     */
    public function inbox(ApiKey $agent, int $wait = 0): array
    {
        $wait     = max(0, min($wait, config('agent_comms.inbox_max_wait')));
        $deadline = microtime(true) + $wait;
        $pollMs   = max(200, config('agent_comms.inbox_poll_ms'));

        // Polling the inbox doubles as the availability heartbeat: an agent
        // that keeps a poll loop running stays "online"; one that stops is
        // considered stale by the directory.
        $this->comms->heartbeat($agent);

        do {
            $messages = $this->unreadMessages($agent);
            $pending  = $this->links->pending($agent);

            if ($messages->isNotEmpty() || $pending->isNotEmpty() || $wait === 0) {
                return ['messages' => $messages, 'pending_links' => $pending];
            }

            if (microtime(true) >= $deadline) {
                break;
            }
            usleep($pollMs * 1000);
        } while (microtime(true) < $deadline);

        return ['messages' => collect(), 'pending_links' => collect()];
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
