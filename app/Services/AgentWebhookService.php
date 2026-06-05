<?php

namespace App\Services;

use App\Models\AgentPresence;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * S3c — optional outbound webhook. When an agent opened comms with a callback_url,
 * ProjectHub POSTs a best-effort "wake" the moment a message/handshake arrives,
 * so server-side runtimes (with an HTTP endpoint) get push without holding an MCP
 * session. This is a nudge, NOT delivery: the payload carries no message body —
 * the runtime fetches via GET /agents/inbox. The message is already stored and
 * also delivered via the inbox long-poll, so a failed webhook loses nothing.
 */
class AgentWebhookService
{
    /** Fire the wake after the response is sent (no worker, non-blocking). */
    public function wake(string $recipientId, array $payload): void
    {
        $presence = AgentPresence::find($recipientId);
        if (! $presence || $presence->status !== 'available' || empty($presence->callback_url)) {
            return;
        }

        $url    = $presence->callback_url;
        $secret = $presence->callback_secret;

        defer(fn () => $this->deliver($url, $payload, $secret));
    }

    /** Synchronous, signed POST. Public so it is unit-testable. */
    public function deliver(string $url, array $payload, ?string $secret): void
    {
        $body    = json_encode($payload);
        $headers = ['X-ProjectHub-Event' => 'agent.inbox'];

        if ($secret) {
            $headers['X-ProjectHub-Signature'] = 'sha256=' . hash_hmac('sha256', $body, $secret);
        }

        try {
            Http::timeout(4)
                ->connectTimeout(2)
                ->withHeaders($headers)
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (\Throwable $e) {
            // Best-effort: the message is stored and delivered via the inbox.
            Log::warning('Agent webhook failed', ['url' => $url, 'error' => $e->getMessage()]);
        }
    }
}
