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

        $stats = [
            'projects'     => Project::whereHas('workspace', fn($q) => $q->where('org_id', $orgId))->count(),
            'open_tasks'   => Task::whereHas('project.workspace', fn($q) => $q->where('org_id', $orgId))
                                  ->whereNotIn('status', ['done'])->count(),
            'blocked_tasks' => Task::whereHas('project.workspace', fn($q) => $q->where('org_id', $orgId))
                                   ->where('status', 'blocked')->count(),
            'agents'       => ApiKey::where('org_id', $orgId)->where('owner_type', 'agent')->count(),
        ];

        $recentEvents = ActivityEvent::whereHas('actor', fn($q) => $q->where('org_id', $orgId))
            ->with('actor:id,model,client_type,pilot')
            ->orderByDesc('created_at')
            ->limit(20)
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
            'stats'        => $stats,
            'recentEvents' => $recentEvents,
        ]);
    }
}
