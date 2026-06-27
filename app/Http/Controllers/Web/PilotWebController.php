<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AgentMemory;
use App\Models\AgentSession;
use App\Models\ApiKey;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PilotWebController extends Controller
{
    private const UNASSIGNED = '— unassigned —';

    // Per-pilot rollup: aggregate the whole org BY THE HUMAN behind the agents,
    // so the owner can track their team's footprint at a glance.
    public function index(Request $request): Response
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $orgId  = $apiKey->org_id;

        $online = now()->subSeconds(90);

        // key id -> stable pilot IDENTITY (pilot_id when set; else a normalized
        // name) so free-text casing/spacing variants don't fragment one human.
        // We also remember a human DISPLAY name per identity.
        $allKeys = ApiKey::where('org_id', $orgId)->get(['id', 'pilot', 'pilot_id']);
        $identityOf = [];
        $displayOf  = [];
        foreach ($allKeys as $k) {
            [$id, $name] = $this->identity($k->pilot_id, $k->pilot);
            $identityOf[$k->id] = $id;
            // Prefer the first non-unassigned, non-empty display we encounter.
            if (!isset($displayOf[$id]) || ($displayOf[$id] === self::UNASSIGNED && $name !== self::UNASSIGNED)) {
                $displayOf[$id] = $name;
            }
        }
        $orgKeyIds = $allKeys->pluck('id');

        // Bucket factory keyed by identity; the human display name lives in 'pilot'.
        $pilots = [];
        $bucket = function (string $identity) use (&$pilots, &$displayOf): int {
            if (!isset($pilots[$identity])) {
                $pilots[$identity] = [
                    'pilot'              => $displayOf[$identity] ?? self::UNASSIGNED,
                    'pilot_contact'      => null,
                    'agents_count'       => 0,
                    'online_count'       => 0,
                    'handles'            => [],
                    'last_active_at'     => null,   // Carbon|null, stripped before render
                    'memories_created'   => 0,
                    'skills_created'     => 0,
                    'credentials_created'=> 0,
                    'memories_by_type'   => [],
                    'query_hits_total'   => 0,
                    'reinforced_total'   => 0,
                    'sessions_count'     => 0,
                    'open_threads_count' => 0,
                ];
            }
            return 0;
        };

        // 1) Agents grouped by pilot.
        $agents = ApiKey::where('org_id', $orgId)
            ->where('owner_type', 'agent')
            ->with('presence:agent_id,status,last_heartbeat')
            ->orderByDesc('last_active_at')
            ->get();

        foreach ($agents as $a) {
            [$id] = $this->identity($a->pilot_id, $a->pilot);
            $bucket($id);
            $p = &$pilots[$id];

            $p['agents_count']++;
            if (count($p['handles']) < 6) {
                $p['handles'][] = $a->handle ?? $a->model;
            }

            $isOnline = $a->presence
                && (($a->presence->status === 'available')
                    || ($a->presence->last_heartbeat && $a->presence->last_heartbeat->gt($online)));
            if ($isOnline) {
                $p['online_count']++;
            }

            if ($a->last_active_at && (!$p['last_active_at'] || $a->last_active_at->gt($p['last_active_at']))) {
                $p['last_active_at'] = $a->last_active_at;
            }
            if ($p['pilot_contact'] === null && $a->pilot_contact) {
                $p['pilot_contact'] = $a->pilot_contact;
            }
            unset($p);
        }

        // 2+3) Memories — scope by org workspaces, attribute by created_by -> pilot.
        $workspaceIds = Workspace::where('org_id', $orgId)->pluck('id');

        $memories = AgentMemory::whereIn('workspace_id', $workspaceIds)
            ->get(['created_by', 'type', 'query_hits', 'reinforced_count']);

        foreach ($memories as $m) {
            $id = $identityOf[$m->created_by] ?? self::UNASSIGNED;
            $bucket($id);
            $p = &$pilots[$id];

            $p['memories_created']++;
            $type = $m->type ?: 'other';
            $p['memories_by_type'][$type] = ($p['memories_by_type'][$type] ?? 0) + 1;
            if ($type === 'skill')      $p['skills_created']++;
            if ($type === 'credential') $p['credentials_created']++;
            $p['query_hits_total'] += (int) $m->query_hits;
            $p['reinforced_total'] += (int) $m->reinforced_count;
            unset($p);
        }

        // Sessions — count per pilot; open threads only for live (active/paused) ones.
        $sessions = AgentSession::whereIn('api_key_id', $orgKeyIds)
            ->get(['api_key_id', 'status', 'open_threads']);

        foreach ($sessions as $s) {
            $id = $identityOf[$s->api_key_id] ?? self::UNASSIGNED;
            $bucket($id);
            $p = &$pilots[$id];

            $p['sessions_count']++;
            if (in_array($s->status, ['active', 'paused'], true)) {
                $p['open_threads_count'] += count($s->open_threads ?? []);
            }
            unset($p);
        }

        // Finalize: humanize dates, normalize the type map, sort.
        $list = collect(array_values($pilots))->map(function ($p) {
            $p['last_active'] = $p['last_active_at']?->diffForHumans();
            unset($p['last_active_at']);
            $p['memories_by_type'] = collect($p['memories_by_type'])
                ->map(fn($n, $type) => ['type' => $type, 'n' => $n])
                ->sortByDesc('n')->values()->all();
            return $p;
        });

        $sorted = $list->sort(function ($a, $b) {
            // Unassigned always sinks to the bottom.
            $au = $a['pilot'] === self::UNASSIGNED;
            $bu = $b['pilot'] === self::UNASSIGNED;
            if ($au !== $bu) return $au ? 1 : -1;
            // Most active first: memories, then fleet size.
            return [$b['memories_created'], $b['agents_count']] <=> [$a['memories_created'], $a['agents_count']];
        })->values();

        $totals = [
            'pilots_count'  => $sorted->count(),
            'agents_total'  => (int) $sorted->sum('agents_count'),
            'memories_total'=> (int) $sorted->sum('memories_created'),
            'skills_total'  => (int) $sorted->sum('skills_created'),
        ];

        return Inertia::render('Pilots/Index', [
            'pilots' => $sorted,
            'totals' => $totals,
        ]);
    }

    /**
     * Stable identity + human display for a pilot.
     * Identity = pilot_id when set (survives free-text variants), else a
     * normalized name key, else UNASSIGNED. Display = the human-facing name.
     *
     * @return array{0:string,1:string} [identity, display]
     */
    private function identity(?string $pilotId, ?string $pilot): array
    {
        $name = ($pilot === null || trim($pilot) === '') ? self::UNASSIGNED : trim($pilot);

        if ($pilotId) {
            return ['pid:' . $pilotId, $name];
        }
        if ($name === self::UNASSIGNED) {
            return [self::UNASSIGNED, self::UNASSIGNED];
        }
        return ['name:' . mb_strtolower($name), $name];
    }
}
