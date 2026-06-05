<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    // GET /api/v1/events?since=ISO&project_id=
    // Audit filters: ?actor=<api_key_id>&event_type=a,b&entity_type=memory&from=ISO&to=ISO&order=desc
    public function index(Request $request): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        $order = $request->input('order') === 'desc' ? 'desc' : 'asc';

        $query = ActivityEvent::with('actor')
            ->whereHas('actor', fn($q) => $q->where('org_id', $apiKey->org_id))
            ->orderBy('created_at', $order);

        if ($request->filled('since')) {
            $query->where('created_at', '>', $request->since);
        }

        // ── Audit filters ────────────────────────────────────────────────
        if ($request->filled('actor')) {
            $query->where('actor_api_key_id', $request->actor);
        }
        if ($request->filled('event_type')) {
            $query->whereIn('event_type', array_filter(explode(',', $request->event_type)));
        }
        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        if ($request->filled('project_id')) {
            $query->where(fn($q) =>
                $q->where(fn($q2) =>
                    $q2->where('entity_type', 'project')->where('entity_id', $request->project_id)
                )->orWhere(fn($q2) =>
                    $q2->where('entity_type', 'task')
                       ->whereHas('actor') // already scoped to org
                       ->whereIn('entity_id', function ($sub) use ($request) {
                           $sub->select('id')->from('tasks')
                               ->where('project_id', $request->project_id);
                       })
                )
            );
        }

        $events = $query->limit($request->integer('limit', 100))->get();

        return response()->json([
            'data' => $events->map(fn($e) => [
                'id'             => $e->id,
                'type'           => $e->event_type,
                'entity_type'    => $e->entity_type,
                'resource_id'    => $e->entity_id,
                'actor_api_key'  => $e->actor?->id,
                'actor_handle'   => $e->actor?->handle,
                'actor_model'    => $e->actor_model,
                'ip'             => $e->ip_address,
                'payload'        => $e->payload,
                'timestamp'      => $e->created_at,
            ]),
            'meta' => [
                'count'  => $events->count(),
                'latest' => $events->last()?->created_at,
            ],
        ]);
    }
}
