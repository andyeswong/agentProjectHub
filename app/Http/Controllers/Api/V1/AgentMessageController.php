<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\InteractsWithComms;
use App\Services\AgentMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentMessageController extends Controller
{
    use InteractsWithComms;

    public function __construct(private AgentMessageService $messages) {}

    // POST /api/v1/agents/messages — send within an open link
    public function send(Request $request): JsonResponse
    {
        $agent = $this->agent($request);
        $data = $request->validate([
            'link_id'         => 'required|uuid',
            'body'            => 'nullable|string',
            'meta'            => 'nullable|array',
            'refs'            => 'nullable|array',
            'refs.*.type'     => 'required_with:refs|in:task,memory,project',
            'refs.*.id'       => 'required_with:refs|string',
            'type'            => 'nullable|in:message,system,request,response',
            'priority'        => 'nullable|in:normal,urgent',
            'correlation_id'  => 'nullable|string|max:255',
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        // Require at least a body or structured meta/refs.
        if (empty($data['body']) && empty($data['meta']) && empty($data['refs'])) {
            return response()->json([
                'error' => 'A message needs at least body, meta, or refs.',
                'code'  => 'empty_message',
            ], 422);
        }

        $message = $this->messages->send($agent, $data['link_id'], $data);

        return response()->json(['status' => 'sent', 'message' => $message->toPublicArray()], 201);
    }

    // POST /api/v1/agents/messages/rpc — send a request and block for the response
    public function rpc(Request $request): JsonResponse
    {
        $agent = $this->agent($request);
        $data = $request->validate([
            'link_id'        => 'required|uuid',
            'body'           => 'nullable|string',
            'meta'           => 'nullable|array',
            'refs'           => 'nullable|array',
            'refs.*.type'    => 'required_with:refs|in:task,memory,project',
            'refs.*.id'      => 'required_with:refs|string',
            'priority'       => 'nullable|in:normal,urgent',
            'correlation_id' => 'nullable|string|max:255',
            'timeout'        => 'nullable|integer|min:1|max:25',
        ]);

        if (empty($data['body']) && empty($data['meta']) && empty($data['refs'])) {
            return response()->json([
                'error' => 'A request needs at least body, meta, or refs.',
                'code'  => 'empty_message',
            ], 422);
        }

        $result = $this->messages->rpc($agent, $data['link_id'], $data, $data['timeout'] ?? 25);

        return response()->json([
            'status'         => $result['timed_out'] ? 'timeout' : 'responded',
            'correlation_id' => $result['correlation_id'],
            'request'        => $result['request']->toPublicArray(),
            'response'       => $result['response']?->toPublicArray(),
            '_meta' => [
                'hint' => $result['timed_out']
                    ? 'No response within timeout. The peer may still reply; poll agent_inbox for a type=response message with this correlation_id.'
                    : 'Got the response. The peer replied with agent_send(type=response, correlation_id).',
            ],
        ]);
    }

    // GET /api/v1/agents/links/{id}/messages — paginated link history
    public function history(Request $request, string $id): JsonResponse
    {
        $agent = $this->agent($request);
        $data = $request->validate([
            'before' => 'nullable|date',
            'limit'  => 'nullable|integer|min:1|max:100',
        ]);

        $msgs = $this->messages->history($agent, $id, $data['before'] ?? null, $data['limit'] ?? 50);

        return response()->json([
            'data'  => $msgs->map->toPublicArray()->values(),
            '_meta' => [
                'count'       => $msgs->count(),
                'next_before' => $msgs->last()?->created_at,
                'hint'        => 'Newest-first. Page back with ?before=<next_before>. Includes read + unread messages.',
            ],
        ]);
    }

    // GET /api/v1/agents/inbox?wait=25 — unread messages + pending handshakes
    public function inbox(Request $request): JsonResponse
    {
        $agent = $this->agent($request);
        $wait = (int) $request->query('wait', 0);

        $result = $this->messages->inbox($agent, $wait);

        return response()->json([
            'messages'      => $result['messages']->map->toPublicArray()->values(),
            'pending_links' => $result['pending_links']->map->toPublicArray()->values(),
            '_meta' => [
                'unread'        => $result['messages']->count(),
                'pending'       => $result['pending_links']->count(),
                'has_urgent'    => $result['messages']->contains(fn ($m) => $m->priority === 'urgent'),
                'long_poll'     => $wait > 0,
                'hint'          => 'Ack messages with POST /agents/inbox/ack { ids:[...] }. Respond to pending_links with accept/reject. If has_urgent, surface to your pilot immediately.',
            ],
        ]);
    }

    // POST /api/v1/agents/inbox/ack — mark messages read
    public function ack(Request $request): JsonResponse
    {
        $agent = $this->agent($request);
        $data = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'uuid',
        ]);

        $acked = $this->messages->ack($agent, $data['ids']);

        return response()->json(['status' => 'ok', 'acked' => $acked]);
    }
}
