<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\InteractsWithComms;
use App\Services\AgentLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentLinkController extends Controller
{
    use InteractsWithComms;

    public function __construct(private AgentLinkService $links) {}

    // POST /api/v1/agents/links — request a handshake { target, intent? }
    public function request(Request $request): JsonResponse
    {
        $agent = $this->agent($request);
        $data = $request->validate([
            'target' => 'required|string|max:255',  // target handle
            'intent' => 'nullable|string|max:2000',
        ]);

        $link = $this->links->request($agent, $data['target'], $data['intent'] ?? null);

        return response()->json([
            'status' => $link->status,
            'link'   => $link->toPublicArray(),
            '_meta'  => [
                'hint' => $link->status === 'open'
                    ? 'A link with this agent was already open; reusing it. Send via POST /agents/messages.'
                    : "Handshake sent. The target's pilot must accept it. Poll GET /agents/links to watch for status=open.",
            ],
        ], 201);
    }

    // GET /api/v1/agents/links?status=open — links this agent is a party to
    public function index(Request $request): JsonResponse
    {
        $agent = $this->agent($request);
        $data = $request->validate([
            'status' => 'nullable|in:pending,open,rejected,closed,expired',
        ]);

        $links = $this->links->listFor($agent, $data['status'] ?? null);

        return response()->json([
            'data' => $links->map->toPublicArray()->values(),
        ]);
    }

    // GET /api/v1/agents/links/pending — incoming handshakes awaiting my decision
    public function pending(Request $request): JsonResponse
    {
        $agent = $this->agent($request);
        $pending = $this->links->pending($agent);

        return response()->json([
            'data'  => $pending->map->toPublicArray()->values(),
            '_meta' => [
                'hint' => 'These are incoming handshakes. Surface them to your pilot; then POST /agents/links/{id}/accept or /reject.',
            ],
        ]);
    }

    // POST /api/v1/agents/links/{id}/accept
    public function accept(Request $request, string $id): JsonResponse
    {
        $agent = $this->agent($request);
        $link = $this->links->accept($agent, $id);

        return response()->json([
            'status' => 'open',
            'link'   => $link->toPublicArray(),
            '_meta'  => ['hint' => 'Link open. Exchange messages via POST /agents/messages { link_id }.'],
        ]);
    }

    // POST /api/v1/agents/links/{id}/reject
    public function reject(Request $request, string $id): JsonResponse
    {
        $agent = $this->agent($request);
        $link = $this->links->reject($agent, $id);

        return response()->json(['status' => 'rejected', 'link' => $link->toPublicArray()]);
    }

    // POST /api/v1/agents/links/{id}/close — "cierra enlace"
    public function close(Request $request, string $id): JsonResponse
    {
        $agent = $this->agent($request);
        $data = $request->validate(['reason' => 'nullable|string|max:255']);
        $link = $this->links->close($agent, $id, $data['reason'] ?? null);

        return response()->json(['status' => 'closed', 'link' => $link->toPublicArray()]);
    }
}
