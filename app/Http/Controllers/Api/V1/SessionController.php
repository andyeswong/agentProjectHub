<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AgentMemory;
use App\Models\AgentSession;
use App\Models\ApiKey;
use App\Services\ActivityEventService;
use App\Services\ConsolidatorService;
use App\Services\EmbeddingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Session management — the episodic layer + warmup/resume.
 *
 * checkpoint: agent pushes a compact session record (compress at WRITE, not at
 *             read). Idempotent per (api_key, external_id).
 * index:      warmup list — recent + (optionally) relevance-ranked sessions for
 *             the PILOT (cross-agent), so a fresh agent can offer to resume.
 * resume:     returns the session + a consolidated "where we left off" briefing
 *             (reuses the consolidator) + its linked memories.
 */
class SessionController extends Controller
{
    public function __construct(
        private EmbeddingService $embedder,
        private ConsolidatorService $consolidator,
        private ActivityEventService $events,
    ) {}

    // POST /api/v1/sessions/checkpoint
    public function checkpoint(Request $request): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        $data = $request->validate([
            'external_id'        => 'required|string|max:255',
            'title'              => 'nullable|string|max:255',
            'summary'            => 'required|string',
            'open_threads'       => 'nullable|array',
            'open_threads.*'     => 'string',
            'linked_memory_ids'  => 'nullable|array',
            'linked_memory_ids.*'=> 'uuid',
            'linked_task_ids'    => 'nullable|array',
            'status'             => 'nullable|in:active,paused,done',
            'cwd'                => 'nullable|string|max:500',
        ]);

        // Scrub secrets before they land in a session record (transcripts leak
        // tokens). Reuse the consolidator's redactor.
        $summary = $this->consolidator->redactSecrets($data['summary']);
        $threads = array_map(fn ($t) => $this->consolidator->redactSecrets($t), $data['open_threads'] ?? []);

        $embedding = $this->embedder->embed(($data['title'] ?? '') . ' ' . $summary);

        $existing = AgentSession::where('api_key_id', $apiKey->id)
            ->where('external_id', $data['external_id'])
            ->first();

        $attrs = [
            'pilot_id'          => $apiKey->pilot_id,
            'api_key_id'        => $apiKey->id,
            'workspace_id'      => $apiKey->workspace_id,
            'external_id'       => $data['external_id'],
            'agent_handle'      => $apiKey->handle,
            'title'             => $data['title'] ?? ($existing->title ?? null),
            'summary'           => $summary,
            'embedding'         => $embedding,
            'open_threads'      => $threads,
            'linked_memory_ids' => $data['linked_memory_ids'] ?? ($existing->linked_memory_ids ?? null),
            'linked_task_ids'   => $data['linked_task_ids'] ?? ($existing->linked_task_ids ?? null),
            'status'            => $data['status'] ?? ($existing->status ?? 'active'),
            'cwd'               => $data['cwd'] ?? ($existing->cwd ?? null),
            'last_active_at'    => now(),
            'started_at'        => $existing->started_at ?? now(),
            'ended_at'          => (($data['status'] ?? null) === 'done') ? now() : ($existing->ended_at ?? null),
        ];

        $session = $existing ? tap($existing)->update($attrs) : AgentSession::create($attrs);

        $this->events->record('session.checkpoint', 'session', $session->id, $apiKey, [
            'external_id' => $data['external_id'],
            'status'      => $session->status,
            'open'        => count($threads),
        ]);

        return response()->json([
            'status'  => $existing ? 'updated' : 'created',
            'session' => $session->fresh()->toPublicArray(),
            '_meta'   => [
                'pilot_id'  => $apiKey->pilot_id,
                'embedded'  => $embedding !== null,
                'hint'      => 'Session checkpointed. On next wake, session_list surfaces it (recency + relevance) for resume.',
            ],
        ], $existing ? 200 : 201);
    }

    // GET /api/v1/sessions   ?q=&limit=&status=&open_only=&include_current=
    public function index(Request $request): JsonResponse
    {
        $apiKey  = $request->attributes->get('api_key');
        $keyIds  = $this->pilotKeyIds($apiKey);
        $limit   = (int) $request->integer('limit', 5);
        $limit   = max(1, min($limit, 50));

        $q = AgentSession::whereIn('api_key_id', $keyIds);

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->boolean('open_only')) {
            $q->whereNotNull('open_threads')->whereRaw("json_array_length(open_threads) > 0");
        }
        if (! $request->boolean('include_current') && $request->filled('external_id')) {
            $q->where('external_id', '!=', $request->external_id);
        }

        $relevanceQuery = $request->query('q');
        $ranked = null;

        if ($relevanceQuery) {
            $qVec = $this->embedder->embed($relevanceQuery);
            $rows = $q->orderByDesc('last_active_at')->limit(80)->get();
            if ($qVec) {
                $ranked = $rows->map(function (AgentSession $s) use ($qVec) {
                    $score = $s->embedding ? $this->embedder->cosineSimilarity($qVec, $s->embedding) : 0.0;
                    return ['session' => $s, 'score' => round($score, 4)];
                })->sortByDesc('score')->take($limit)->values();
            }
        }

        if ($ranked === null) {
            $ranked = $q->orderByDesc('last_active_at')->limit($limit)->get()
                ->map(fn (AgentSession $s) => ['session' => $s, 'score' => null])->values();
        }

        return response()->json([
            'mode'     => $relevanceQuery ? 'relevance' : 'recency',
            'sessions' => $ranked->map(fn ($r) => [
                'session' => $r['session']->toPublicArray(),
                'score'   => $r['score'],
            ])->values(),
            '_meta' => [
                'pilot_id'      => $apiKey->pilot_id,
                'pilot_scoped'  => $apiKey->pilot_id !== null,
                'agent_keys'    => count($keyIds),
                'returned'      => $ranked->count(),
                'hint'          => $apiKey->pilot_id
                    ? 'Scoped to ALL your agents (per-pilot). Pass q=<topic> for relevance, open_only=true for unfinished work, then resume one.'
                    : 'This token has no pilot_id — scoped to THIS agent only. Merge it under a pilot to get cross-agent continuity.',
            ],
            'next_steps' => [
                ['action' => 'Resume a session', 'method' => 'GET', 'endpoint' => '/api/v1/sessions/{id}/resume'],
            ],
        ]);
    }

    // GET /api/v1/sessions/{id}/resume
    public function resume(Request $request, string $id): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');
        $keyIds = $this->pilotKeyIds($apiKey);

        $session = AgentSession::whereIn('api_key_id', $keyIds)->findOrFail($id);

        // Gather linked memories (masked) to fold into the resume briefing.
        $linked = collect();
        if (!empty($session->linked_memory_ids)) {
            $linked = AgentMemory::whereIn('id', $session->linked_memory_ids)->get();
        }

        // Build the episodic block: the session gist + open threads + linked
        // memory bodies, then reuse the consolidator (delta-extractor) to emit a
        // compact "where we left off" briefing.
        $blocks = ["### SESSION: {$session->title}\n{$session->summary}"];
        if (!empty($session->open_threads)) {
            $blocks[] = "### OPEN THREADS\n- " . implode("\n- ", $session->open_threads);
        }
        foreach ($linked as $m) {
            $pub = $m->toPublicArray();
            $blocks[] = "### [id: {$m->id}] ({$m->type}) {$m->label}\n" . mb_substr((string)($pub['content'] ?? ''), 0, 1500);
        }
        $block = implode("\n\n", $blocks);

        $digest = $this->consolidator->enabled()
            ? $this->consolidator->consolidate($block, "resume session: " . ($session->title ?? $session->external_id))
            : ['ok' => false, 'knowledge' => null, 'error' => 'consolidator disabled'];

        $session->update(['last_active_at' => now(), 'status' => $session->status === 'done' ? 'done' : 'active']);
        $this->events->record('session.resumed', 'session', $session->id, $apiKey, ['title' => $session->title]);

        return response()->json([
            'session'       => $session->toPublicArray(),
            'resume_digest' => $digest['ok'] ? $digest['knowledge'] : null,
            'linked_memories' => $linked->map(fn ($m) => [
                'id' => $m->id, 'key' => $m->memory_key, 'type' => $m->type, 'label' => $m->label,
            ])->values(),
            '_meta' => [
                'open_threads'      => $session->open_threads ?? [],
                'digest_model'      => $digest['ok'] ? ($digest['model'] ?? null) : null,
                'digest_error'      => $digest['ok'] ? null : ($digest['error'] ?? null),
                'hint'              => 'Pick up from open_threads. resume_digest is the consolidated context; the raw session.summary is verbatim.',
            ],
        ]);
    }

    /**
     * All api_key ids belonging to the same pilot (cross-agent continuity).
     * Falls back to just this key when the token has no pilot_id.
     */
    private function pilotKeyIds(ApiKey $apiKey): array
    {
        if (!$apiKey->pilot_id) {
            return [$apiKey->id];
        }
        return ApiKey::where('pilot_id', $apiKey->pilot_id)->pluck('id')->all();
    }
}
