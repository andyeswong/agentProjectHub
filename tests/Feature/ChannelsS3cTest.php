<?php

namespace Tests\Feature;

use App\Models\AgentPresence;
use App\Models\ApiKey;
use App\Models\Organization;
use App\Services\AgentWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChannelsS3cTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private ApiKey $alice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org   = Organization::create(['name' => 'Acme', 'slug' => 'acme']);
        $this->alice = $this->agent('alice');
    }

    private function agent(string $handle): ApiKey
    {
        $raw = 'sk_test_' . $handle . '_' . Str::random(8);
        return ApiKey::create([
            'key'         => $raw,
            'key_hash'    => hash('sha256', $raw),
            'org_id'      => $this->org->id,
            'owner_type'  => 'agent',
            'model'       => $handle,
            'client_type' => 'api',
            'handle'      => $handle,
            'permissions' => ['read', 'write', 'comms'],
            'rate_limit'  => 120,
        ]);
    }

    private function hdr(ApiKey $k): array
    {
        return ['Authorization' => 'Bearer ' . $k->key];
    }

    // ── comms_open stores / close clears the callback ───────────────────────

    public function test_comms_open_stores_callback_and_close_clears_it(): void
    {
        $this->postJson('/api/v1/agents/comms/open', [
            'callback_url'    => 'https://runtime.example/hook',
            'callback_secret' => 'supersecret',
        ], $this->hdr($this->alice))
            ->assertOk()
            ->assertJsonPath('presence.webhook', 'set');

        $p = AgentPresence::find($this->alice->id);
        $this->assertSame('https://runtime.example/hook', $p->callback_url);
        $this->assertSame('supersecret', $p->callback_secret);

        // The secret is never serialized back out.
        $this->assertArrayNotHasKey('callback_secret', $p->toArray());

        $this->postJson('/api/v1/agents/comms/close', [], $this->hdr($this->alice))->assertOk();
        $p->refresh();
        $this->assertNull($p->callback_url);
        $this->assertNull($p->callback_secret);
    }

    public function test_comms_open_rejects_bad_callback_url(): void
    {
        $this->postJson('/api/v1/agents/comms/open', [
            'callback_url' => 'not-a-url',
        ], $this->hdr($this->alice))->assertStatus(422);
    }

    // ── deliver(): signed POST ──────────────────────────────────────────────

    public function test_deliver_posts_signed_payload(): void
    {
        Http::fake();
        $payload = ['event' => 'message', 'link_id' => 'abc', 'priority' => 'urgent'];
        $secret  = 'topsecret1';

        app(AgentWebhookService::class)->deliver('https://runtime.example/hook', $payload, $secret);

        Http::assertSent(function ($request) use ($secret) {
            $expected = 'sha256=' . hash_hmac('sha256', $request->body(), $secret);
            return $request->url() === 'https://runtime.example/hook'
                && $request->hasHeader('X-ProjectHub-Event', 'agent.inbox')
                && $request->hasHeader('X-ProjectHub-Signature', $expected);
        });
    }

    public function test_deliver_without_secret_is_unsigned(): void
    {
        Http::fake();
        app(AgentWebhookService::class)->deliver('https://runtime.example/hook', ['event' => 'message'], null);

        Http::assertSent(fn ($request) => ! $request->hasHeader('X-ProjectHub-Signature'));
    }

    // ── wake(): guards ──────────────────────────────────────────────────────

    public function test_wake_does_nothing_without_a_callback(): void
    {
        Http::fake();
        AgentPresence::create([
            'agent_id' => $this->alice->id, 'status' => 'available', 'last_heartbeat' => now(),
        ]);

        app(AgentWebhookService::class)->wake($this->alice->id, ['event' => 'message']);

        Http::assertNothingSent();
    }

    public function test_wake_does_nothing_when_unavailable(): void
    {
        Http::fake();
        AgentPresence::create([
            'agent_id' => $this->alice->id, 'status' => 'unavailable', 'last_heartbeat' => now(),
            'callback_url' => 'https://runtime.example/hook',
        ]);

        app(AgentWebhookService::class)->wake($this->alice->id, ['event' => 'message']);

        Http::assertNothingSent();
    }
}
