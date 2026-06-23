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
- MERGE AGGRESSIVELY. Do NOT reformat each memory into its own rules. Collapse
  overlapping memories into the FEWEST general rules possible. If three memories
  describe one subject, they become one rule set, not three. Maximize density.
- Prefer the general rule over the instance.
- CITATIONS: every line ends with [src: id] or [src: id1, id2]. A citation
  contains ONLY comma-separated source ids — NOTHING else. NEVER write a
  question mark, alternatives, uncertainty, hedging, or any prose inside a
  citation. If unsure which source, pick the single most likely id. No orphans.
- Be terse. This output will live in a context window; tokens are the cost.
- Output ONLY the four sections. No preamble, reasoning, or closing remarks.
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

    /**
     * Redact high-confidence credential patterns from text before it leaves the
     * box. Covers secrets that live in NON-sensitive memories (which masking by
     * is_sensitive does not catch). Conservative: prefixed tokens + explicit
     * key=value / "X is Y" secret assignments. Free-text secrets with no marker
     * still require flagging the memory is_sensitive.
     */
    public function redactSecrets(string $text): string
    {
        $patterns = [
            '/\b(?:ghp|gho|ghs|ghu|ghr)_[A-Za-z0-9]{6,}/'                               => '[REDACTED:github-pat]',
            '/\bgithub_pat_[A-Za-z0-9_]{20,}/'                                          => '[REDACTED:github-pat]',
            '/\bsk-[A-Za-z0-9_\-]{12,}/'                                                => '[REDACTED:api-key]',
            '/\bfrgo_[A-Za-z0-9]{12,}/'                                                 => '[REDACTED:frgo-token]',
            '/\bAKIA[0-9A-Z]{16}\b/'                                                    => '[REDACTED:aws-key]',
            '/\bxox[baprs]-[A-Za-z0-9\-]{10,}/'                                         => '[REDACTED:slack-token]',
            '/-----BEGIN[A-Z ]+PRIVATE KEY-----[\s\S]*?-----END[A-Z ]+PRIVATE KEY-----/' => '[REDACTED:private-key]',
            '/\bBearer\s+[A-Za-z0-9._\-]{20,}/i'                                        => 'Bearer [REDACTED]',
            // key: value / key=value secret assignments
            '/\b(pass(?:word|phrase|wd)?|secret|api[_-]?key|access[_-]?token|auth[_-]?token|client[_-]?secret)\b\s*[:=]\s*["\'`]?[^\s"\'`]{4,}/i' => '$1: [REDACTED]',
            // "password is X" / "passphrase is `X`"
            '/\b(pass(?:word|phrase|wd)?|secret|token)\b(\s+is\s+)["\'`]?[^\s"\'`,;.]{4,}/i' => '$1$2[REDACTED]',
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $text);
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

        // Defence in depth: AgentMemory::toPublicArray only masks is_sensitive
        // memories. Secrets embedded in NON-sensitive memories (config/fact
        // notes) would otherwise reach the external LLM verbatim. Scrub known
        // credential patterns from EVERY memory before the call.
        $maskedMemories = $this->redactSecrets($maskedMemories);

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
