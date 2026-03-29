<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use Inertia\Inertia;
use Inertia\Response;

class PublicDashboardController extends Controller
{
    public function index(): Response
    {
        $orgs = Organization::withCount([
                'workspaces',
                'apiKeys',
            ])
            ->with(['workspaces.projects' => function ($q) {
                $q->where('status', 'active');
            }])
            ->orderBy('name')
            ->get()
            ->map(function ($org) {
                $projectIds = $org->workspaces->flatMap(fn($w) => $w->projects->pluck('id'));

                $taskStats = Task::whereIn('project_id', $projectIds)
                    ->whereNull('archived_at')
                    ->selectRaw("
                        COUNT(*) as total,
                        SUM(status = 'done') as done,
                        SUM(status = 'blocked') as blocked,
                        SUM(status != 'done') as open
                    ")
                    ->first();

                return [
                    'name'       => $org->name,
                    'slug'       => $org->slug,
                    'projects'   => $projectIds->count(),
                    'agents'     => $org->api_keys_count,
                    'task_stats' => [
                        'total'   => (int) ($taskStats->total   ?? 0),
                        'done'    => (int) ($taskStats->done    ?? 0),
                        'open'    => (int) ($taskStats->open    ?? 0),
                        'blocked' => (int) ($taskStats->blocked ?? 0),
                    ],
                ];
            });

        return Inertia::render('Public/BoardIndex', [
            'orgs'  => $orgs,
            'total' => [
                'orgs'  => $orgs->count(),
                'tasks' => $orgs->sum(fn($o) => $o['task_stats']['total']),
                'open'  => $orgs->sum(fn($o) => $o['task_stats']['open']),
                'done'  => $orgs->sum(fn($o) => $o['task_stats']['done']),
            ],
        ]);
    }

    public function show(string $slug): Response
    {
        $org = Organization::where('slug', $slug)->firstOrFail();

        $columns = ['backlog', 'todo', 'in_progress', 'done', 'blocked'];

        $projects = Project::whereHas('workspace', fn($q) => $q->where('org_id', $org->id))
            ->where('status', 'active')
            ->with(['workspace:id,name,slug'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($p) use ($columns) {
                $tasks = $p->tasks()
                    ->select('id', 'project_id', 'title', 'status', 'priority', 'assignee_id', 'due_date')
                    ->with('assignee:id,model,pilot')
                    ->orderByRaw("FIELD(priority,'critical','high','medium','low')")
                    ->get()
                    ->groupBy('status');

                $kanban = [];
                foreach ($columns as $col) {
                    $kanban[$col] = ($tasks[$col] ?? collect())->values()->map(fn($t) => [
                        'id'       => $t->id,
                        'title'    => $t->title,
                        'priority' => $t->priority,
                        'due_date' => $t->due_date?->format('Y-m-d'),
                        'assignee' => $t->assignee ? [
                            'model' => $t->assignee->model,
                            'pilot' => $t->assignee->pilot,
                        ] : null,
                    ]);
                }

                $total   = array_sum(array_map(fn($c) => count($kanban[$c]), $columns));
                $done    = count($kanban['done']);
                $blocked = count($kanban['blocked']);

                return [
                    'id'          => $p->id,
                    'name'        => $p->name,
                    'description' => $p->description,
                    'workspace'   => $p->workspace?->name,
                    'kanban'      => $kanban,
                    'task_counts' => [
                        'total'   => $total,
                        'done'    => $done,
                        'open'    => $total - $done,
                        'blocked' => $blocked,
                    ],
                ];
            });

        return Inertia::render('Public/OrgBoard', [
            'org'      => ['name' => $org->name, 'slug' => $org->slug],
            'projects' => $projects,
            'stats'    => [
                'projects'    => $projects->count(),
                'total_tasks' => $projects->sum(fn($p) => $p['task_counts']['total']),
                'open'        => $projects->sum(fn($p) => $p['task_counts']['open']),
                'done'        => $projects->sum(fn($p) => $p['task_counts']['done']),
                'blocked'     => $projects->sum(fn($p) => $p['task_counts']['blocked']),
            ],
        ]);
    }
}
