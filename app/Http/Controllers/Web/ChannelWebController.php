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
    // GET /channels — agent-to-agent coordination: links + recent messages.
    public function index(Request $request): Response
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $orgId  = $apiKey->org_id;

        $links = AgentLink::where('org_id', $orgId)
            ->with(['initiator:id,handle,model', 'target:id,handle,model'])
            ->orderByDesc('updated_at')->limit(50)->get()
            ->map(fn($l) => [
                'id'        => $l->id,
                'status'    => $l->status,
                'intent'    => $l->intent,
                'initiator' => $l->initiator?->handle ?? $l->initiator?->model,
                'target'    => $l->target?->handle ?? $l->target?->model,
                'updated'   => $l->updated_at?->diffForHumans(),
            ]);

        $messages = AgentMessage::where('org_id', $orgId)
            ->with('from:id,handle,model')
            ->orderByDesc('created_at')->limit(60)->get()
            ->map(fn($m) => [
                'id'       => $m->id,
                'from'     => $m->from?->handle ?? $m->from?->model,
                'type'     => $m->type,
                'priority' => $m->priority,
                'body'     => is_string($m->body) ? mb_substr($m->body, 0, 240) : '',
                'time_ago' => $m->created_at->diffForHumans(),
            ]);

        return Inertia::render('Channels/Index', [
            'links'    => $links,
            'messages' => $messages,
        ]);
    }
}
