<?php

namespace App\Services;

use App\Exceptions\AgentCommsException;
use App\Models\AgentLink;
use App\Models\ApiKey;
use Illuminate\Support\Collection;

/**
 * Handshake / link lifecycle for 1:1 agent comms.
 * pending -> open | rejected | expired ; open -> closed.
 * Both agents must be AVAILABLE to form a link; messages require an OPEN link.
 */
class AgentLinkService
{
    public function __construct(
        private AgentCommsService $comms,
        private ActivityEventService $events,
    ) {}

    public function request(ApiKey $initiator, string $targetHandle, ?string $intent): AgentLink
    {
        if (! $this->comms->isAvailable($initiator)) {
            throw AgentCommsException::make('initiator_unavailable',
                'Open comms first (POST /agents/comms/open) before requesting a link.', 409);
        }

        $target = ApiKey::where('org_id', $initiator->org_id)
            ->where('handle', $targetHandle)
            ->whereNull('revoked_at')
            ->first();

        if (! $target) {
            throw AgentCommsException::make('target_not_found',
                "No agent with handle '{$targetHandle}' in your organization.", 404);
        }
        if ($target->id === $initiator->id) {
            throw AgentCommsException::make('self_link', 'Cannot open a link with yourself.', 422);
        }
        if (! $this->comms->isAvailable($target)) {
            throw AgentCommsException::make('target_unavailable',
                "Agent '{$targetHandle}' has not opened comms and is not reachable.", 409);
        }

        $this->expireStale();

        // Idempotent: reuse an existing open link or a live pending request.
        if ($open = $this->openLinkBetween($initiator->id, $target->id)) {
            return $open;
        }
        $pending = AgentLink::where('initiator_id', $initiator->id)
            ->where('target_id', $target->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();
        if ($pending) {
            return $pending;
        }

        $link = AgentLink::create([
            'org_id'           => $initiator->org_id,
            'initiator_id'     => $initiator->id,
            'target_id'        => $target->id,
            'status'           => 'pending',
            'intent'           => $intent,
            'requested_at'     => now(),
            'expires_at'       => now()->addSeconds(config('agent_comms.handshake_ttl')),
            'last_activity_at' => now(),
        ]);

        $this->events->record('agent.link_requested', 'agent_link', $link->id, $initiator, [
            'target' => $target->handle,
            'intent' => $intent,
        ]);

        return $link->load(['initiator', 'target']);
    }

    /** Incoming handshakes awaiting this agent's (pilot's) decision. */
    public function pending(ApiKey $agent): Collection
    {
        $this->expireStale();

        return AgentLink::where('target_id', $agent->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->with('initiator')
            ->orderByDesc('requested_at')
            ->get();
    }

    public function accept(ApiKey $agent, string $linkId): AgentLink
    {
        $link = $this->findForAgent($linkId, $agent->id);

        if ($link->target_id !== $agent->id) {
            throw AgentCommsException::make('not_target', 'Only the target agent can accept this link.', 403);
        }
        if ($link->status !== 'pending') {
            throw AgentCommsException::make('not_pending', "Link is '{$link->status}', cannot accept.", 409);
        }
        if ($link->expires_at && $link->expires_at->isPast()) {
            $link->update(['status' => 'expired']);
            throw AgentCommsException::make('expired', 'This handshake has expired.', 409);
        }
        if (! $this->comms->isAvailable($agent)) {
            throw AgentCommsException::make('unavailable', 'Open comms before accepting a link.', 409);
        }

        $link->update([
            'status'           => 'open',
            'responded_at'     => now(),
            'opened_at'        => now(),
            'last_activity_at' => now(),
        ]);

        $this->events->record('agent.link_accepted', 'agent_link', $link->id, $agent, []);

        return $link->load(['initiator', 'target']);
    }

    public function reject(ApiKey $agent, string $linkId): AgentLink
    {
        $link = $this->findForAgent($linkId, $agent->id);

        if ($link->target_id !== $agent->id) {
            throw AgentCommsException::make('not_target', 'Only the target agent can reject this link.', 403);
        }
        if ($link->status !== 'pending') {
            throw AgentCommsException::make('not_pending', "Link is '{$link->status}', cannot reject.", 409);
        }

        $link->update(['status' => 'rejected', 'responded_at' => now()]);
        $this->events->record('agent.link_rejected', 'agent_link', $link->id, $agent, []);

        return $link;
    }

    public function close(ApiKey $agent, string $linkId, ?string $reason = null): AgentLink
    {
        $link = $this->findForAgent($linkId, $agent->id);

        if (! in_array($link->status, ['pending', 'open'], true)) {
            throw AgentCommsException::make('not_active', "Link is already '{$link->status}'.", 409);
        }

        $link->update([
            'status'       => 'closed',
            'closed_at'    => now(),
            'closed_by'    => $agent->id,
            'close_reason' => $reason ?? 'closed_by_party',
        ]);

        $this->events->record('agent.link_closed', 'agent_link', $link->id, $agent, [
            'reason' => $link->close_reason,
        ]);

        return $link;
    }

    public function listFor(ApiKey $agent, ?string $status = null): Collection
    {
        $this->expireStale();

        return AgentLink::where(fn ($q) => $q->where('initiator_id', $agent->id)->orWhere('target_id', $agent->id))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with(['initiator', 'target'])
            ->orderByDesc('last_activity_at')
            ->get();
    }

    /** The OPEN link this agent may send on, or throw. */
    public function openLinkForSend(ApiKey $agent, string $linkId): AgentLink
    {
        $link = $this->findForAgent($linkId, $agent->id);
        if (! $link->isOpen()) {
            throw AgentCommsException::make('link_not_open',
                "Link is '{$link->status}'. Messages require an open link.", 409);
        }
        return $link;
    }

    public function openLinkBetween(string $a, string $b): ?AgentLink
    {
        return AgentLink::where('status', 'open')
            ->where(function ($q) use ($a, $b) {
                $q->where(fn ($s) => $s->where('initiator_id', $a)->where('target_id', $b))
                  ->orWhere(fn ($s) => $s->where('initiator_id', $b)->where('target_id', $a));
            })
            ->first();
    }

    private function findForAgent(string $linkId, string $agentId): AgentLink
    {
        $link = AgentLink::where('id', $linkId)
            ->where(fn ($q) => $q->where('initiator_id', $agentId)->orWhere('target_id', $agentId))
            ->first();

        if (! $link) {
            throw AgentCommsException::make('link_not_found', 'Link not found or you are not a party to it.', 404);
        }
        return $link;
    }

    /** Expire pending handshakes past TTL and idle-close stale open links. */
    public function expireStale(): void
    {
        AgentLink::where('status', 'pending')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);

        $idleBefore = now()->subSeconds(config('agent_comms.idle_ttl'));
        AgentLink::where('status', 'open')
            ->where('last_activity_at', '<=', $idleBefore)
            ->update(['status' => 'closed', 'closed_at' => now(), 'close_reason' => 'idle_timeout']);
    }
}
