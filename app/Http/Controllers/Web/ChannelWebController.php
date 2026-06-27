<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AgentLink;
use App\Models\AgentMessage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChannelWebController extends Controller
{
    // GET /channels — agent-to-agent coordination grouped by CONVERSATION.
    // A conversation = one link (its intent/timeline IS the handshake) + all its messages.
    public function index(Request $request): Response
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $orgId  = $apiKey->org_id;
        $selfId = $apiKey->id;

        $links = AgentLink::where('org_id', $orgId)
            ->with(['initiator:id,handle,model', 'target:id,handle,model'])
            ->orderByDesc('last_activity_at')
            ->orderByDesc('updated_at')
            ->limit(50)->get();

        // All messages for these links in one query, grouped by link in PHP (no N+1).
        $messagesByLink = AgentMessage::where('org_id', $orgId)
            ->whereIn('link_id', $links->pluck('id'))
            ->with('from:id,handle,model')
            ->orderBy('created_at')->get()
            ->groupBy('link_id');

        $handle = fn($k) => $k?->handle ?? $k?->model ?? 'unknown';

        $conversations = $links->map(function ($l) use ($messagesByLink, $selfId, $handle) {
            $msgs = ($messagesByLink->get($l->id) ?? collect())->map(fn($m) => [
                'id'       => $m->id,
                'from'     => $handle($m->from),
                'mine'     => $m->from_id === $selfId,
                'type'     => $m->type,
                'priority' => $m->priority,
                'body'     => is_string($m->body) ? $m->body : '',
                'time_ago' => $m->created_at?->diffForHumans(),
                'at'       => $m->created_at?->toIso8601String(),
            ])->values();

            $last = $msgs->last();

            return [
                'id'             => $l->id,
                'status'         => $l->status,
                'intent'         => $l->intent,
                'initiator'      => $handle($l->initiator),
                'target'         => $handle($l->target),
                'initiated_by_me'=> $l->initiator_id === $selfId,
                // handshake timeline
                'requested_at'   => $l->requested_at?->toIso8601String(),
                'opened_at'      => $l->opened_at?->toIso8601String(),
                'closed_at'      => $l->closed_at?->toIso8601String(),
                'close_reason'   => $l->close_reason,
                'updated'        => ($l->last_activity_at ?? $l->updated_at)?->diffForHumans(),
                // thread
                'messages'       => $msgs,
                'message_count'  => $msgs->count(),
                'last_preview'   => $last ? mb_substr($last['body'], 0, 80) : null,
                'last_from'      => $last['from'] ?? null,
            ];
        });

        return Inertia::render('Channels/Index', [
            'conversations' => $conversations,
        ]);
    }
}
