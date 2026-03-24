<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Task;
use App\Services\ActivityEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function __construct(private ActivityEventService $events) {}

    // POST /api/v1/tasks/:id/comments
    public function store(Request $request, string $taskId): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        $task = Task::whereHas('project.workspace', fn($q) => $q->where('org_id', $apiKey->org_id))
            ->findOrFail($taskId);

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

        return response()->json($comment->load('actor'), 201);
    }
}
