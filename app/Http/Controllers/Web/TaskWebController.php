<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use App\Models\Comment;
use App\Models\Task;
use App\Services\ActivityEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class TaskWebController extends Controller
{
    public function __construct(private ActivityEventService $events) {}

    public function show(Request $request, string $id): Response
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $orgId  = $apiKey->org_id;

        $task = Task::whereHas('project.workspace', fn($q) => $q->where('org_id', $orgId))
            ->with([
                'project:id,name,workspace_id',
                'project.workspace:id,name,slug',
                'assignee:id,model,pilot,client_type',
                'creator:id,model,pilot',
                'comments' => fn($q) => $q->orderBy('created_at'),
                'comments.actor:id,model,pilot,client_type',
            ])
            ->findOrFail($id);

        $timeline = ActivityEvent::where('entity_type', 'task')
            ->where('entity_id', $task->id)
            ->with('actor:id,model,pilot')
            ->orderBy('created_at')
            ->get()
            ->map(fn($e) => [
                'id'          => $e->id,
                'type'        => $e->event_type,
                'actor_model' => $e->actor_model,
                'actor_pilot' => $e->actor?->pilot,
                'payload'     => $e->payload,
                'timestamp'   => $e->created_at->toISOString(),
                'time_ago'    => $e->created_at->diffForHumans(),
            ]);

        return Inertia::render('Tasks/Show', [
            'task'     => $task,
            'timeline' => $timeline,
        ]);
    }

    // POST /tasks/{id}/comments — pilot leaves a typed supervision comment.
    public function comment(Request $request, string $id)
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $task = $this->findTask($apiKey, $id);

        $data = $request->validate([
            'text' => 'required|string',
            'type' => 'nullable|in:instruction,correction,question,approval,general',
        ]);

        $comment = Comment::create([
            'task_id'          => $task->id,
            'actor_api_key_id' => $apiKey->id,
            'text'             => $data['text'],
            'type'             => $data['type'] ?? 'general',
        ]);

        $this->events->record('task.commented', 'task', $task->id, $apiKey, [
            'comment_id'   => $comment->id,
            'comment_type' => $comment->type,
        ], $request->ip());

        return Redirect::route('tasks.show', $task->id);
    }

    // PATCH /tasks/{id} — pilot edits status / title / description / priority.
    public function update(Request $request, string $id)
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $task = $this->findTask($apiKey, $id);

        $data = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'status'      => 'sometimes|in:backlog,todo,in_progress,done,blocked',
            'priority'    => 'sometimes|in:low,medium,high,critical',
        ]);

        $before = $task->status;
        $task->update($data);

        if (isset($data['status']) && $data['status'] !== $before) {
            $this->events->record('task.status_changed', 'task', $task->id, $apiKey, [
                'from' => $before, 'to' => $data['status'],
            ], $request->ip());
        } else {
            $this->events->record('task.updated', 'task', $task->id, $apiKey, [
                'fields' => array_keys($data),
            ], $request->ip());
        }

        return Redirect::back();
    }

    private function findTask($apiKey, string $id): Task
    {
        return Task::whereHas('project.workspace', fn($q) => $q->where('org_id', $apiKey->org_id))
            ->findOrFail($id);
    }
}
