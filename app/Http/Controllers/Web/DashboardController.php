<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use App\Models\AgentLink;
use App\Models\AgentMemory;
use App\Models\AgentMessage;
use App\Models\AgentSession;
use App\Models\ApiKey;
use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $orgId  = $apiKey->org_id;

        $taskBase = Task::whereHas('project.workspace', fn($q) => $q->where('org_id', $orgId))
            ->whereNull('archived_at');

        $totalTasks   = (clone $taskBase)->count();
        $doneTasks    = (clone $taskBase)->where('status', 'done')->count();
        $openTasks    = (clone $taskBase)->whereNotIn('status', ['done'])->count();
        $blockedTasks = (clone $taskBase)->where('status', 'blocked')->count();

        $workspaceIds = Workspace::where('org_id', $orgId)->pluck('id');

        $stats = [
            'projects'      => Project::whereHas('workspace', fn($q) => $q->where('org_id', $orgId))->count(),
            'open_tasks'    => $openTasks,
            'done_tasks'    => $doneTasks,
            'total_tasks'   => $totalTasks,
            'blocked_tasks' => $blockedTasks,
            'agents'        => ApiKey::where('org_id', $orgId)->where('owner_type', 'agent')->count(),
            'done_percent'  => $totalTasks > 0 ? round(($doneTasks / $totalTasks) * 100) : 0,
            'memories'      => AgentMemory::whereIn('workspace_id', $workspaceIds)->count(),
        ];

        $recentProjects = Project::whereHas('workspace', fn($q) => $q->where('org_id', $orgId))
            ->where('status', 'active')
            ->withCount([
                'tasks as total_tasks'   => fn($q) => $q->whereNull('archived_at'),
                'tasks as done_tasks'    => fn($q) => $q->whereNull('archived_at')->where('status', 'done'),
                'tasks as open_tasks'    => fn($q) => $q->whereNull('archived_at')->whereNotIn('status', ['done']),
                'tasks as blocked_tasks' => fn($q) => $q->whereNull('archived_at')->where('status', 'blocked'),
            ])
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get()
            ->map(fn($p) => [
                'id'           => $p->id,
                'name'         => $p->name,
                'description'  => $p->description,
                'total_tasks'  => $p->total_tasks,
                'done_tasks'   => $p->done_tasks,
                'open_tasks'   => $p->open_tasks,
                'blocked_tasks'=> $p->blocked_tasks,
                'done_percent' => $p->total_tasks > 0 ? round(($p->done_tasks / $p->total_tasks) * 100) : 0,
                'updated_at'   => $p->updated_at->diffForHumans(),
            ]);

        $recentEvents = ActivityEvent::whereHas('actor', fn($q) => $q->where('org_id', $orgId))
            ->with('actor:id,model,client_type,pilot')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get()
            ->map(fn($e) => [
                'id'          => $e->id,
                'type'        => $e->event_type,
                'entity_type' => $e->entity_type,
                'resource_id' => $e->entity_id,
                'actor_model' => $e->actor_model,
                'actor_pilot' => $e->actor?->pilot,
                'payload'     => $e->payload,
                'timestamp'   => $e->created_at->toISOString(),
                'time_ago'    => $e->created_at->diffForHumans(),
            ]);

        return Inertia::render('Dashboard', [
            'stats'          => $stats,
            'recentProjects' => $recentProjects,
            'recentEvents'   => $recentEvents,
            'fleet'          => $this->fleet($orgId),
            'memory'         => $this->memoryPulse($workspaceIds),
            'coordination'   => $this->coordination($orgId),
        ]);
    }

    // FLEET — the real org asset: which agents worked recently + who's their pilot.
    private function fleet(string $orgId): array
    {
        $online = now()->subSeconds(90);

        $agents = ApiKey::where('org_id', $orgId)
            ->where('owner_type', 'agent')
            ->whereNull('revoked_at')
            ->with('presence:agent_id,status,last_heartbeat')
            ->orderByRaw('last_active_at IS NULL, last_active_at DESC')
            ->limit(8)->get()
            ->map(fn($a) => [
                'handle'      => $a->handle ?? $a->model,
                'pilot'       => $a->pilot,
                'model'       => $a->model,
                'last_active' => $a->last_active_at?->diffForHumans(),
                'available'   => $a->presence?->status === 'available',
                'online'      => $a->presence && $a->presence->last_heartbeat && $a->presence->last_heartbeat->gt($online),
            ]);

        return [
            'agents'    => $agents,
            'total'     => ApiKey::where('org_id', $orgId)->where('owner_type', 'agent')->whereNull('revoked_at')->count(),
            'available' => ApiKey::where('org_id', $orgId)->where('owner_type', 'agent')
                ->whereHas('presence', fn($q) => $q->where('status', 'available'))->count(),
        ];
    }

    // MEMORY PULSE — the brain: size, growth, mix, and what gets consulted most.
    private function memoryPulse($workspaceIds): array
    {
        $base = fn() => AgentMemory::whereIn('workspace_id', $workspaceIds);

        $byType = $base()->select('type', DB::raw('count(*) as n'))
            ->groupBy('type')->orderByDesc('n')->get()
            ->map(fn($r) => ['type' => $r->type, 'n' => (int) $r->n]);

        $project = fn($m) => [
            'id'         => $m->id,
            'key'        => $m->memory_key,
            'label'      => $m->label,
            'type'       => $m->type,
            'query_hits' => (int) $m->query_hits,
            'reinforced' => (int) $m->reinforced_count,
        ];

        $topConsulted = $base()->where('query_hits', '>', 0)
            ->orderByDesc('query_hits')->limit(5)
            ->get(['id', 'memory_key', 'label', 'type', 'query_hits', 'reinforced_count'])
            ->map($project);

        $topReinforced = $base()->where('reinforced_count', '>', 0)
            ->orderByDesc('reinforced_count')->limit(5)
            ->get(['id', 'memory_key', 'label', 'type', 'query_hits', 'reinforced_count'])
            ->map($project);

        return [
            'total'          => $base()->count(),
            'last_7d'        => $base()->where('created_at', '>=', now()->subDays(7))->count(),
            'by_type'        => $byType,
            'top_consulted'  => $topConsulted,
            'top_reinforced' => $topReinforced,
        ];
    }

    // COORDINATION — agent-to-agent links + recent messages + open sessions.
    private function coordination(string $orgId): array
    {
        $recent = AgentMessage::where('org_id', $orgId)
            ->with('from:id,handle,model,pilot')
            ->orderByDesc('created_at')->limit(5)->get()
            ->map(fn($m) => [
                'from'     => $m->from?->handle ?? $m->from?->model,
                'pilot'    => $m->from?->pilot,
                'preview'  => is_string($m->body) ? mb_substr($m->body, 0, 70) : '',
                'priority' => $m->priority,
                'time_ago' => $m->created_at?->diffForHumans(),
            ]);

        $openSessions = AgentSession::whereIn('api_key_id', ApiKey::where('org_id', $orgId)->pluck('id'))
            ->whereIn('status', ['active', 'paused'])
            ->get(['agent_handle', 'title', 'open_threads', 'last_active_at'])
            ->filter(fn($s) => !empty($s->open_threads))
            ->take(5)
            ->map(fn($s) => [
                'handle'      => $s->agent_handle,
                'title'       => $s->title,
                'threads'     => count($s->open_threads ?? []),
                'last_active' => $s->last_active_at?->diffForHumans(),
            ])->values();

        return [
            'open_links'     => AgentLink::where('org_id', $orgId)->where('status', 'open')->count(),
            'pending_links'  => AgentLink::where('org_id', $orgId)->where('status', 'pending')->count(),
            'recent_messages'=> $recent,
            'open_sessions'  => $openSessions,
        ];
    }
}
