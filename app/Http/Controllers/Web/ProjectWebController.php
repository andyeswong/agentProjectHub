<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectWebController extends Controller
{
    public function index(Request $request): Response
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $orgId  = $apiKey->org_id;

        $query = Project::whereHas('workspace', fn($q) => $q->where('org_id', $orgId))
            ->with(['workspace:id,name,slug', 'creator:id,model,pilot']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $projects = $query->orderByDesc('updated_at')->get()->map(fn($p) => [
            'id'          => $p->id,
            'name'        => $p->name,
            'description' => $p->description,
            'status'      => $p->status,
            'workspace'   => $p->workspace,
            'created_by'  => $p->creator?->pilot ?? $p->creator?->model,
            'task_counts' => [
                'total'   => $p->tasks()->count(),
                'open'    => $p->tasks()->whereNotIn('status', ['done'])->count(),
                'done'    => $p->tasks()->where('status', 'done')->count(),
                'blocked' => $p->tasks()->where('status', 'blocked')->count(),
            ],
            'updated_at'  => $p->updated_at->toISOString(),
            'created_at'  => $p->created_at->toISOString(),
        ]);

        return Inertia::render('Projects/Index', [
            'projects'      => $projects,
            'filters'       => ['status' => $request->status],
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $orgId  = $apiKey->org_id;

        $project = Project::whereHas('workspace', fn($q) => $q->where('org_id', $orgId))
            ->with(['workspace:id,name,slug', 'creator:id,model,pilot'])
            ->findOrFail($id);

        $tasks = $project->tasks()
            ->with(['assignee:id,model,pilot,client_type', 'creator:id,model,pilot'])
            ->orderByRaw("FIELD(priority,'critical','high','medium','low')")
            ->get()
            ->groupBy('status');

        $columns = ['backlog', 'todo', 'in_progress', 'done', 'blocked'];
        $kanban  = [];
        foreach ($columns as $col) {
            $kanban[$col] = ($tasks[$col] ?? collect())->values();
        }

        return Inertia::render('Projects/Show', [
            'project' => [
                'id'          => $project->id,
                'name'        => $project->name,
                'description' => $project->description,
                'status'      => $project->status,
                'workspace'   => $project->workspace,
                'created_by'  => $project->creator?->pilot ?? $project->creator?->model,
                'task_counts' => [
                    'total'   => $project->tasks()->count(),
                    'open'    => $project->tasks()->whereNotIn('status', ['done'])->count(),
                    'done'    => $project->tasks()->where('status', 'done')->count(),
                    'blocked' => $project->tasks()->where('status', 'blocked')->count(),
                ],
                'updated_at'  => $project->updated_at->toISOString(),
            ],
            'kanban'  => $kanban,
        ]);
    }
}
