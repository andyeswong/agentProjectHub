<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use App\Models\ApiKey;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
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

        $stats = [
            'projects'      => Project::whereHas('workspace', fn($q) => $q->where('org_id', $orgId))->count(),
            'open_tasks'    => $openTasks,
            'done_tasks'    => $doneTasks,
            'total_tasks'   => $totalTasks,
            'blocked_tasks' => $blockedTasks,
            'agents'        => ApiKey::where('org_id', $orgId)->where('owner_type', 'agent')->count(),
            'done_percent'  => $totalTasks > 0 ? round(($doneTasks / $totalTasks) * 100) : 0,
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
        ]);
    }
}
