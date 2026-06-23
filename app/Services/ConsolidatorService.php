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
You are a DELTA EXTRACTOR briefing an EXPERT agent. The reader is a highly
capable engineer/agent that ALREADY knows general procedures, tools,
languages and best practices (how to SSH, deploy a binary, write Laravel,
reason about systems). Do NOT teach it any of that.

Your ONLY job is to surface what the reader CANNOT know on its own — the
private, local, environment-specific reality and the surprises. Three
things qualify:
  1. ACCESS & SPECIFICS it cannot derive: hosts, IPs, ports, repo paths,
     fixed client values, credentials (as vault handles, never raw).
  2. COUNTER-DEFAULT facts: where THIS environment deviates from what a
     competent engineer would sensibly assume by default.
  3. CORRECTIONS / error-trails: "the obvious X fails, the real one is Y".

If a competent engineer would already know it, or could derive it unaided,
OMIT IT. A generic procedure ("scp the binary, restart the service") is
WASTE — only note the step where THIS environment forces a deviation
("must pkill -9 first, an orphan holds the port"). You brief the terrain,
not the craft.

# INPUT
A set of memory items. Each has: id, type, label, content, tags.

# OUTPUT — exactly these sections, nothing else:

## LOCAL RULES
ONLY env-specific or counter-default operational rules — things the reader
would get WRONG following its defaults. One rule = one line.
Form: "WHEN <context> -> <do/expect> [src: id1,id2]".
Merge overlapping memories into ONE rule. OMIT any rule a competent agent
would already perform correctly unaided. If a whole procedure is standard
except one twist, emit ONLY the twist.

## REFERENCES
Specifics the reader must reach but cannot derive: endpoints, hosts, repo
paths, ports, fixed values. Format: "<name>: <value> [src: id]".
For SECRETS: never inline a value. Emit "<name>: [vault:<mask>] [src: id]".

## GOTCHAS
Counter-default surprises, failure modes, corrections/error-trails — each
with the trigger that surfaces it. "<symptom/trigger> -> <cause/fix> [src: id]".
Prefer "tried X, it failed, real is Y" over a bare fact: the error-trail is
the highest-value delta (it pre-empts the mistake the default would make).

## PROVENANCE
List every source id consumed, so the result is reconstructible and gateable.

# HARD RULES
- DELTA ONLY: assume an expert reader. Emit ONLY non-derivable content
  (access/specifics + counter-default + corrections). Ruthlessly drop
  anything derivable from general competence. Fewer, higher-signal lines
  beat completeness. An empty section is fine if there is no delta for it.
- NEVER invent. Only consolidate what is present. If two memories conflict,
  emit both with a "CONFLICT" tag — do not pick silently.
- MERGE: collapse overlapping memories into the fewest lines; never one-rule-
  per-memory.
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
