<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\InteractsWithComms;
use App\Services\AgentCommsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentCommsController extends Controller
{
    use InteractsWithComms;

    public function __construct(private AgentCommsService $comms) {}

    // GET /api/v1/agents?available=1 — directory of agents in the org
    public function directory(Request $request): JsonResponse
    {
        $agent = $this->agent($request);
        $availableOnly = filter_var($request->query('available', false), FILTER_VALIDATE_BOOLEAN);

        return response()->json([
            'data'  => $this->comms->directory($agent->org_id, $availableOnly),
            '_meta' => [
                'self'   => $agent->handle,
                'filter' => $availableOnly ? 'available_only' : 'all',
                'hint'   => 'Address an agent by its handle in POST /agents/links { target }. Only agents with status=available can be linked.',
            ],
        ]);
    }

    // POST /api/v1/agents/comms/open — pilot-authorized: become reachable
    public function open(Request $request): JsonResponse
    {
        $agent = $this->agent($request);
        $data = $request->validate(['meta' => 'nullable|array']);

        $presence = $this->comms->open($agent, $data['meta'] ?? []);

        return response()->json([
            'status'   => 'available',
            'presence' => [
                'handle'          => $agent->handle,
                'status'          => $presence->status,
                'available_since' => $presence->available_since,
            ],
            '_meta' => [
                'hint' => 'You can now receive handshakes. Poll GET /agents/inbox (or ?wait=N to long-poll) to surface incoming links/messages to your pilot.',
            ],
        ]);
    }

    // POST /api/v1/agents/comms/close — go offline; closes pending/open links
    public function close(Request $request): JsonResponse
    {
        $agent = $this->agent($request);
        $this->comms->close($agent);

        return response()->json([
            'status' => 'unavailable',
            '_meta'  => ['hint' => 'Comms closed. All pending and open links were closed.'],
        ]);
    }

    // GET /api/v1/agents/comms/status — own presence
    public function status(Request $request): JsonResponse
    {
        $agent = $this->agent($request);
        $presence = $this->comms->presence($agent);

        return response()->json([
            'handle'          => $agent->handle,
            'status'          => $presence?->status ?? 'unavailable',
            'available_since' => $presence?->available_since,
            'last_heartbeat'  => $presence?->last_heartbeat,
        ]);
    }
}
