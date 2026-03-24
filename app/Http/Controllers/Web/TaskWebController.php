<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use App\Models\Task;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskWebController extends Controller
{
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
}
