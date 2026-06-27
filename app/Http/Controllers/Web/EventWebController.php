<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventWebController extends Controller
{
    // GET /events — the org's immutable activity log, optionally filtered by type.
    public function index(Request $request): Response
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $keyIds = ApiKey::where('org_id', $apiKey->org_id)->pluck('id');

        $query = ActivityEvent::whereIn('actor_api_key_id', $keyIds)->with('actor:id,model,pilot');
        if ($request->filled('type')) {
            $query->where('event_type', 'like', $request->type . '%');
        }

        $events = $query->orderByDesc('created_at')->limit(150)->get()->map(fn($e) => [
            'id'          => $e->id,
            'type'        => $e->event_type,
            'entity_type' => $e->entity_type,
            'entity_id'   => $e->entity_id,
            'actor_model' => $e->actor_model,
            'actor_pilot' => $e->actor?->pilot,
            'payload'     => $e->payload,
            'time_ago'    => $e->created_at->diffForHumans(),
        ]);

        // Distinct event-type prefixes for the filter chips.
        $kinds = ActivityEvent::whereIn('actor_api_key_id', $keyIds)
            ->selectRaw("split_part(event_type, '.', 1) as kind")
            ->distinct()->pluck('kind')->filter()->values();

        return Inertia::render('Events/Index', [
            'events'  => $events,
            'kinds'   => $kinds,
            'filter'  => $request->input('type', ''),
        ]);
    }
}
