<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Workspace;
use App\Services\ActivityEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function __construct(private ActivityEventService $events) {}

    // GET /api/v1/projects
    public function index(Request $request): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        $query = Project::whereHas('workspace', fn($q) => $q->where('org_id', $apiKey->org_id))
            ->with(['workspace', 'creator']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('workspace')) {
            $query->whereHas('workspace', fn($q) => $q->where('slug', $request->workspace));
        }

        if ($request->filled('q')) {
            $query->where('name', 'like', "%{$request->q}%");
        }

        $sortField = in_array($request->sort, ['created_at', 'updated_at', 'name']) ? $request->sort : 'updated_at';
        $query->orderByDesc($sortField);

        $projects = $query->paginate($request->integer('limit', 20));

        return response()->json([
            'data' => $projects->map(fn($p) => [
                'id'           => $p->id,
                'name'         => $p->name,
                'description'  => $p->description,
                'status'       => $p->status,
                'workspace'    => $p->workspace->slug,
                'task_counts'  => [
                    'total'       => $p->tasks()->count(),
                    'open'        => $p->tasks()->whereNotIn('status', ['done'])->count(),
                    'done'        => $p->tasks()->where('status', 'done')->count(),
                    'blocked'     => $p->tasks()->where('status', 'blocked')->count(),
                ],
                'created_by'   => $p->creator->pilot ?? $p->creator->model,
                'updated_at'   => $p->updated_at,
            ]),
            'meta' => ['total' => $projects->total(), 'limit' => $projects->perPage()],
        ]);
    }

    // POST /api/v1/projects
    public function store(Request $request): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        $data = $request->validate([
            'workspace_id' => 'required|uuid|exists:workspaces,id',
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string',
            'status'       => 'nullable|in:active,archived',
        ]);

        $workspace = Workspace::where('id', $data['workspace_id'])
            ->where('org_id', $apiKey->org_id)
            ->firstOrFail();

        $project = Project::create([
            ...$data,
            'created_by' => $apiKey->id,
        ]);

        $this->events->record('project.created', 'project', $project->id, $apiKey, [
            'name' => $project->name,
        ], $request->ip());

        return response()->json($project->load('workspace'), 201);
    }

    // GET /api/v1/projects/:id
    public function show(Request $request, string $id): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        $project = Project::whereHas('workspace', fn($q) => $q->where('org_id', $apiKey->org_id))
            ->with(['workspace', 'creator'])
            ->findOrFail($id);

        return response()->json([
            ...$project->toArray(),
            'task_counts' => [
                'total'   => $project->tasks()->count(),
                'open'    => $project->tasks()->whereNotIn('status', ['done'])->count(),
                'done'    => $project->tasks()->where('status', 'done')->count(),
                'blocked' => $project->tasks()->where('status', 'blocked')->count(),
            ],
        ]);
    }

    // PATCH /api/v1/projects/:id
    public function update(Request $request, string $id): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        $project = Project::whereHas('workspace', fn($q) => $q->where('org_id', $apiKey->org_id))
            ->findOrFail($id);

        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'status'      => 'sometimes|in:active,archived',
        ]);

        $project->update($data);

        $this->events->record('project.updated', 'project', $project->id, $apiKey, $data, $request->ip());

        return response()->json($project);
    }

    public function destroy(string $id): JsonResponse
    {
        return response()->json(['error' => 'Projects cannot be deleted, only archived.'], 405);
    }
}
