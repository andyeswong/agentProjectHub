<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Services\ActivityEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProjectWebController extends Controller
{
    public function __construct(private ActivityEventService $events) {}

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
            ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
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

    // POST /projects — pilot creates a project in the org's first workspace.
    public function store(Request $request)
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string',
            'workspace_id' => 'nullable|uuid',
        ]);

        $ws = \App\Models\Workspace::where('org_id', $apiKey->org_id)
            ->when($data['workspace_id'] ?? null, fn($q, $id) => $q->where('id', $id))
            ->orderBy('name')->firstOrFail();

        $project = Project::create([
            'workspace_id' => $ws->id,
            'created_by'   => $apiKey->id,
            'name'         => $data['name'],
            'description'  => $data['description'] ?? null,
            'status'       => 'active',
        ]);

        $this->events->record('project.created', 'project', $project->id, $apiKey, ['name' => $project->name], $request->ip());
        return Redirect::route('projects.show', $project->id);
    }

    // PATCH /projects/{id} — edit / archive.
    public function update(Request $request, string $id)
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $project = Project::whereHas('workspace', fn($q) => $q->where('org_id', $apiKey->org_id))->findOrFail($id);

        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'status'      => 'sometimes|in:active,archived',
        ]);
        $project->update($data);

        $this->events->record('project.updated', 'project', $project->id, $apiKey, ['fields' => array_keys($data)], $request->ip());
        return Redirect::back();
    }

    // POST /projects/{id}/tasks — pilot adds a task to the board.
    public function createTask(Request $request, string $id)
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $project = Project::whereHas('workspace', fn($q) => $q->where('org_id', $apiKey->org_id))->findOrFail($id);

        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'nullable|in:backlog,todo,in_progress,done,blocked',
            'priority'    => 'nullable|in:low,medium,high,critical',
        ]);

        $task = Task::create([
            'project_id'  => $project->id,
            'created_by'  => $apiKey->id,
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'status'      => $data['status'] ?? 'todo',
            'priority'    => $data['priority'] ?? 'medium',
        ]);

        $this->events->record('task.created', 'task', $task->id, $apiKey, ['title' => $task->title, 'project_id' => $project->id], $request->ip());
        return Redirect::route('projects.show', $project->id);
    }
}
