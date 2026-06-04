<?php

namespace App\Services;

use App\Models\AgentLink;
use App\Models\AgentPresence;
use App\Models\ApiKey;
use Illuminate\Support\Collection;

/**
 * Availability layer of Agent Channels. A pilot opens comms for their agent
 * (comms_open) before the agent is reachable; closing comms tears down any
 * pending/open links.
 */
class AgentCommsService
{
    public function __construct(private ActivityEventService $events) {}

    public function open(ApiKey $agent, array $meta = []): AgentPresence
    {
        $presence = AgentPresence::firstOrNew(['agent_id' => $agent->id]);
        $alreadyAvailable = $presence->exists && $presence->status === 'available';

        $presence->status         = 'available';
        $presence->available_since = $alreadyAvailable ? $presence->available_since : now();
        $presence->last_heartbeat = now();
        if ($meta) {
            $presence->meta = array_merge($presence->meta ?? [], $meta);
        }
        $presence->save();

        if (! $alreadyAvailable) {
            $this->events->record('agent.comms_opened', 'agent_presence', $agent->id, $agent, [
                'handle' => $agent->handle,
            ]);
        }

        return $presence;
    }

    public function heartbeat(ApiKey $agent): void
    {
        AgentPresence::where('agent_id', $agent->id)->update(['last_heartbeat' => now()]);
    }

    /**
     * Go unavailable and close every pending/open link the agent is part of.
     */
    public function close(ApiKey $agent): void
    {
        AgentPresence::where('agent_id', $agent->id)->update(['status' => 'unavailable']);

        AgentLink::where(fn ($q) => $q->where('initiator_id', $agent->id)->orWhere('target_id', $agent->id))
            ->whereIn('status', ['pending', 'open'])
            ->get()
            ->each(function (AgentLink $link) use ($agent) {
                $link->update([
                    'status'       => 'closed',
                    'closed_at'    => now(),
                    'closed_by'    => $agent->id,
                    'close_reason' => 'comms_closed',
                ]);
                $this->events->record('agent.link_closed', 'agent_link', $link->id, $agent, [
                    'reason' => 'comms_closed',
                ]);
            });

        $this->events->record('agent.comms_closed', 'agent_presence', $agent->id, $agent, []);
    }

    public function isAvailable(ApiKey $agent): bool
    {
        return AgentPresence::where('agent_id', $agent->id)->where('status', 'available')->exists();
    }

    public function presence(ApiKey $agent): ?AgentPresence
    {
        return AgentPresence::find($agent->id);
    }

    /**
     * Directory of agents in an org with presence, for discovery.
     */
    public function directory(string $orgId, bool $availableOnly = false): Collection
    {
        $keys = ApiKey::where('org_id', $orgId)->whereNull('revoked_at')->get();
        $presence = AgentPresence::whereIn('agent_id', $keys->pluck('id'))->get()->keyBy('agent_id');
        $staleAfter = now()->subSeconds(config('agent_comms.heartbeat_ttl'));

        return $keys->map(function (ApiKey $k) use ($presence, $staleAfter) {
            $p = $presence->get($k->id);
            $status = $p?->status ?? 'unavailable';
            return [
                'id'             => $k->id,
                'handle'         => $k->handle,
                'model'          => $k->model,
                'model_provider' => $k->model_provider,
                'pilot'          => $k->pilot,
                'status'         => $status,
                'online'         => $status === 'available'
                    && $p?->last_heartbeat !== null
                    && $p->last_heartbeat->greaterThan($staleAfter),
                'available_since' => $p?->available_since,
            ];
        })
            ->when($availableOnly, fn (Collection $c) => $c->where('status', 'available'))
            ->values();
    }
}
