<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Services\ActivityEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(private ActivityEventService $events) {}

    // GET /api/v1/projects/:id/tasks
    public function index(Request $request, string $projectId): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        $project = Project::whereHas('workspace', fn($q) => $q->where('org_id', $apiKey->org_id))
            ->findOrFail($projectId);

        $query = $project->tasks()->with(['assignee', 'creator']);

        // Exclude archived tasks by default; pass ?include_archived=true to show them
        if (!$request->boolean('include_archived')) {
            $query->whereNull('archived_at');
        }

        // status filter: supports "open" as shorthand
        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === 'open') {
                $query->whereNotIn('status', ['done']);
            } else {
                $query->whereIn('status', explode(',', $status));
            }
        }

        if ($request->filled('assignee')) {
            if ($request->assignee === 'me') {
                $query->where('assignee_id', $apiKey->id);
            } elseif ($request->assignee === 'unassigned') {
                $query->whereNull('assignee_id');
            } else {
                $query->where('assignee_id', $request->assignee);
            }
        }

        if ($request->filled('priority')) {
            $query->whereIn('priority', explode(',', $request->priority));
        }

        if ($request->filled('q')) {
            $query->where(fn($q) =>
                $q->where('title', 'like', "%{$request->q}%")
                  ->orWhere('description', 'like', "%{$request->q}%")
            );
        }

        if ($request->filled('created_after')) {
            $query->where('created_at', '>=', $request->created_after);
        }

        if ($request->filled('created_before')) {
            $query->where('created_at', '<=', $request->created_before);
        }

        $tasks = $query->orderByRaw("FIELD(priority, 'critical','high','medium','low')")
            ->orderBy('created_at')
            ->paginate($request->integer('limit', 50));

        return response()->json([
            'data' => $tasks->items(),
            'meta' => ['total' => $tasks->total(), 'limit' => $tasks->perPage()],
        ]);
    }

    // POST /api/v1/projects/:id/tasks
    public function store(Request $request, string $projectId): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        $project = Project::whereHas('workspace', fn($q) => $q->where('org_id', $apiKey->org_id))
            ->findOrFail($projectId);

        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'status'           => 'nullable|in:backlog,todo,in_progress,done,blocked',
            'priority'         => 'nullable|in:low,medium,high,critical',
            'assignee_id'      => 'nullable|uuid|exists:api_keys,id',
            'due_date'         => 'nullable|date',
            'start_date'       => 'nullable|date',
            'estimated_hours'  => 'nullable|numeric|min:0',
            'tags'             => 'nullable|array',
        ]);

        // Allow "me" as shorthand for assignee
        if (($data['assignee_id'] ?? null) === 'me') {
            $data['assignee_id'] = $apiKey->id;
        }

        $task = Task::create([...$data, 'project_id' => $project->id, 'created_by' => $apiKey->id]);

        $this->events->record('task.created', 'task', $task->id, $apiKey, [
            'title'    => $task->title,
            'priority' => $task->priority,
            'status'   => $task->status,
        ], $request->ip());

        return response()->json($task->load(['assignee', 'creator']), 201);
    }

    // POST /api/v1/projects/:id/tasks/batch
    public function batch(Request $request, string $projectId): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        $project = Project::whereHas('workspace', fn($q) => $q->where('org_id', $apiKey->org_id))
            ->findOrFail($projectId);

        $data = $request->validate([
            'tasks'                    => 'required|array|min:1|max:50',
            'tasks.*.title'            => 'required|string|max:255',
            'tasks.*.description'      => 'nullable|string',
            'tasks.*.status'           => 'nullable|in:backlog,todo,in_progress,done,blocked',
            'tasks.*.priority'         => 'nullable|in:low,medium,high,critical',
            'tasks.*.assignee_id'      => 'nullable|string',
            'tasks.*.due_date'         => 'nullable|date',
            'tasks.*.estimated_hours'  => 'nullable|numeric|min:0',
            'tasks.*.tags'             => 'nullable|array',
        ]);

        $created = [];
        $failed  = [];

        foreach ($data['tasks'] as $i => $taskData) {
            try {
                if (($taskData['assignee_id'] ?? null) === 'me') {
                    $taskData['assignee_id'] = $apiKey->id;
                }

                $task = Task::create([
                    ...$taskData,
                    'project_id' => $project->id,
                    'created_by' => $apiKey->id,
                ]);

                $this->events->record('task.created', 'task', $task->id, $apiKey, [
                    'title' => $task->title,
                ], $request->ip());

                $created[] = $task->id;
            } catch (\Exception $e) {
                $failed[] = ['index' => $i, 'error' => $e->getMessage()];
            }
        }

        return response()->json(['created' => $created, 'failed' => $failed], 201);
    }

    // GET /api/v1/tasks/:id
    public function show(Request $request, string $id): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        $task = Task::whereHas('project.workspace', fn($q) => $q->where('org_id', $apiKey->org_id))
            ->with(['project', 'assignee', 'creator', 'comments.actor'])
            ->findOrFail($id);

        $timeline = ActivityEvent::where('entity_type', 'task')
            ->where('entity_id', $task->id)
            ->orderBy('created_at')
            ->get();

        return response()->json([
            ...$task->toArray(),
            'timeline' => $timeline,
        ]);
    }

    // PATCH /api/v1/tasks/:id
    public function update(Request $request, string $id): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        $task = Task::whereHas('project.workspace', fn($q) => $q->where('org_id', $apiKey->org_id))
            ->findOrFail($id);

        $data = $request->validate([
            'title'           => 'sometimes|string|max:255',
            'description'     => 'sometimes|nullable|string',
            'status'          => 'sometimes|in:backlog,todo,in_progress,done,blocked',
            'priority'        => 'sometimes|in:low,medium,high,critical',
            'assignee_id'     => 'sometimes|nullable|uuid|exists:api_keys,id',
            'due_date'        => 'sometimes|nullable|date',
            'start_date'      => 'sometimes|nullable|date',
            'estimated_hours' => 'sometimes|nullable|numeric|min:0',
            'tags'            => 'sometimes|nullable|array',
            'project_id'      => 'sometimes|uuid|exists:projects,id',
        ]);

        // ── Move to another project ───────────────────────────────────────
        if (isset($data['project_id']) && $data['project_id'] !== $task->project_id) {
            $destination = Project::whereHas('workspace', fn($q) => $q->where('org_id', $apiKey->org_id))
                ->find($data['project_id']);

            if (!$destination) {
                return response()->json(['error' => 'Destination project not found or not in your organization.'], 404);
            }

            $fromProject = $task->project_id;
            $task->update(['project_id' => $data['project_id']]);
            unset($data['project_id']);

            $this->events->record('task.moved', 'task', $task->id, $apiKey, [
                'from_project_id' => $fromProject,
                'to_project_id'   => $destination->id,
                'to_project_name' => $destination->name,
            ], $request->ip());

            // If only moving (no other fields), return early
            if (empty($data)) {
                return response()->json($task->fresh(['assignee', 'creator', 'project']));
            }
        }

        $oldStatus = $task->status;
        $task->update($data);

        $eventType = isset($data['status']) && $data['status'] !== $oldStatus
            ? 'task.status_changed'
            : 'task.updated';

        $payload = $eventType === 'task.status_changed'
            ? ['status_changed' => "{$oldStatus} → {$data['status']}"]
            : $data;

        $this->events->record($eventType, 'task', $task->id, $apiKey, $payload, $request->ip());

        return response()->json($task->fresh(['assignee', 'creator', 'project']));
    }

    // POST /api/v1/tasks/:id/archive
    public function archive(Request $request, string $id): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        $task = Task::whereHas('project.workspace', fn($q) => $q->where('org_id', $apiKey->org_id))
            ->findOrFail($id);

        if ($task->isArchived()) {
            return response()->json(['error' => 'Task is already archived.'], 409);
        }

        $data = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $reason = $data['reason'] ?? null;

        $task->update([
            'archived_at'    => now(),
            'archived_by'    => $apiKey->id,
            'archive_reason' => $reason,
        ]);

        // Auto-create a comment so the reason is visible in the task timeline
        if ($reason) {
            Comment::create([
                'task_id'          => $task->id,
                'actor_api_key_id' => $apiKey->id,
                'text'             => "[archived] {$reason}",
                'type'             => 'general',
            ]);
        }

        $this->events->record('task.archived', 'task', $task->id, $apiKey, [
            'reason' => $reason,
        ], $request->ip());

        return response()->json([
            'status'      => 'archived',
            'task_id'     => $task->id,
            'archived_at' => $task->archived_at,
            'reason'      => $reason,
        ]);
    }

    // POST /api/v1/tasks/:id/unarchive
    public function unarchive(Request $request, string $id): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        $task = Task::whereHas('project.workspace', fn($q) => $q->where('org_id', $apiKey->org_id))
            ->findOrFail($id);

        if (!$task->isArchived()) {
            return response()->json(['error' => 'Task is not archived.'], 409);
        }

        $task->update([
            'archived_at'    => null,
            'archived_by'    => null,
            'archive_reason' => null,
        ]);

        $this->events->record('task.unarchived', 'task', $task->id, $apiKey, [], $request->ip());

        return response()->json($task->fresh(['assignee', 'creator', 'project']));
    }

    public function destroy(string $id): JsonResponse
    {
        return response()->json(['error' => 'Tasks cannot be deleted.'], 405);
    }
}
