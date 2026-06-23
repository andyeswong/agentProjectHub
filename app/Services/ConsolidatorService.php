<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ⚠️ EXPERIMENTAL — Knowledge consolidation layer.
 *
 * Takes a set of raw retrieved MEMORIES (episodic, redundant, source-bound)
 * and asks a small/cheap LLM to transform them into KNOWLEDGE (generalized,
 * deduplicated, application-ready rules + references + gotchas + provenance).
 *
 * This is NOT wired into the normal memory_search path. It powers the
 * experimental POST /api/v1/memory/consolidate endpoint only.
 *
 * The LLM is OpenAI-compatible and fully configurable via .env
 * (CONSOLIDATOR_*). Prod default: DeepSeek deepseek-v4-flash.
 *
 * GOVERNANCE: callers MUST pass already-masked memory text. Sensitive memory
 * content is redacted by AgentMemory::toPublicArray() before it reaches here,
 * so raw secrets are never sent to the external model.
 */
class ConsolidatorService
{
    private bool $enabled;
    private string $baseUrl;
    private ?string $apiKey;
    private string $model;
    private int $timeout;
    private int $maxTokens;

    private const SYSTEM_PROMPT = <<<'PROMPT'
# ROLE
You are a KNOWLEDGE CONSOLIDATOR. You convert raw retrieved MEMORIES
(episodic, redundant, source-bound, multi-fact blobs) into KNOWLEDGE
(generalized, deduplicated, atomic, application-ready rules) for a
production AI agent.

You are NOT a summarizer. A summary shortens prose. You TRANSFORM
recall into applicable rules: what the agent should DO when it touches
this subject, stated so it needs no further lookup.

# INPUT
A set of memory items. Each has: id, type, label, content, tags.

# OUTPUT — exactly these sections, nothing else:

## RULES
Atomic, imperative, application-oriented. One rule = one line.
Form: "WHEN <context> -> <do/expect> [src: id1,id2]".
Merge overlapping memories into ONE rule. Generalize recurring patterns
across items into a single rule. Order by how often the agent will need it.

## REFERENCES
Stable values the agent must reach but not memorize verbatim: endpoints,
hosts, repo paths, ports. Format: "<name>: <value> [src: id]".
For SECRETS: never inline a value. Emit "<name>: [vault:<mask>] [src: id]".

## GOTCHAS
Failure modes, postmortems, "don't do X" — each with the trigger that
surfaces it. "<symptom/trigger> -> <cause/avoidance> [src: id]".

## PROVENANCE
List every source id consumed, so the result is reconstructible and gateable.

# HARD RULES
- NEVER invent. Only consolidate what is present. If two memories conflict,
  emit both with a "CONFLICT" tag — do not pick silently.
- Drop nothing load-bearing. Cut redundancy, restated context, narration,
  dates that don't change behavior, and prose scaffolding.
- Prefer the general rule over the instance.
- Every line carries its [src: ...]. No orphan claims.
- Be terse. This output will live in a context window; tokens are the cost.
- Output ONLY the four sections. No preamble, no closing remarks.
PROMPT;

    public function __construct()
    {
        $this->enabled   = (bool) config('services.consolidator.enabled', false);
        $this->baseUrl   = rtrim((string) config('services.consolidator.base_url'), '/');
        $this->apiKey    = config('services.consolidator.api_key');
        $this->model     = (string) config('services.consolidator.model');
        $this->timeout   = (int) config('services.consolidator.timeout', 120);
        $this->maxTokens = (int) config('services.consolidator.max_tokens', 4096);
    }

    public function enabled(): bool
    {
        return $this->enabled && $this->baseUrl !== '' && $this->model !== '';
    }

    public function model(): string
    {
        return $this->model;
    }

    /**
     * Consolidate already-masked raw memory text into knowledge.
     *
     * @return array{ok:bool, knowledge:?string, model:string, usage:?array, error:?string}
     */
    public function consolidate(string $maskedMemories, ?string $queryContext = null): array
    {
        if (!$this->enabled()) {
            return [
                'ok'        => false,
                'knowledge' => null,
                'model'     => $this->model,
                'usage'     => null,
                'error'     => 'Consolidator is disabled or misconfigured. Set CONSOLIDATOR_ENABLED=true and CONSOLIDATOR_* in .env.',
            ];
        }

        $userMsg = $queryContext
            ? "Retrieval context (the query these memories answer): \"{$queryContext}\"\n\n=== MEMORIES ===\n{$maskedMemories}"
            : "=== MEMORIES ===\n{$maskedMemories}";

        try {
            $request = Http::timeout($this->timeout)
                ->acceptJson()
                ->asJson();

            if (!empty($this->apiKey)) {
                $request = $request->withToken($this->apiKey);
            }

            $response = $request->post("{$this->baseUrl}/chat/completions", [
                'model'       => $this->model,
                'temperature' => 0.2,
                'max_tokens'  => $this->maxTokens,
                'messages'    => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ['role' => 'user',   'content' => $userMsg],
                ],
            ]);

            if ($response->failed()) {
                Log::warning('ConsolidatorService: non-2xx from LLM', [
                    'status' => $response->status(),
                    'body'   => mb_substr($response->body(), 0, 500),
                ]);
                return [
                    'ok'        => false,
                    'knowledge' => null,
                    'model'     => $this->model,
                    'usage'     => null,
                    'error'     => "Consolidator LLM returned HTTP {$response->status()}.",
                ];
            }

            $knowledge = $response->json('choices.0.message.content');

            if (!is_string($knowledge) || trim($knowledge) === '') {
                return [
                    'ok'        => false,
                    'knowledge' => null,
                    'model'     => $this->model,
                    'usage'     => null,
                    'error'     => 'Consolidator LLM returned an empty completion.',
                ];
            }

            return [
                'ok'        => true,
                'knowledge' => trim($knowledge),
                'model'     => $this->model,
                'usage'     => $response->json('usage'),
                'error'     => null,
            ];

        } catch (\Exception $e) {
            Log::warning('ConsolidatorService: could not reach LLM', [
                'error'   => $e->getMessage(),
                'base_url'=> $this->baseUrl,
            ]);
            return [
                'ok'        => false,
                'knowledge' => null,
                'model'     => $this->model,
                'usage'     => null,
                'error'     => 'Could not reach the consolidator LLM endpoint.',
            ];
        }
    }
}
