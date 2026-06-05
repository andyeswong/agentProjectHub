<?php

namespace Tests\Feature;

use App\Models\AgentLink;
use App\Models\AgentPresence;
use App\Models\ApiKey;
use App\Models\Organization;
use App\Services\AgentLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChannelsS2Test extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private ApiKey $alice;
    private ApiKey $bob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org   = Organization::create(['name' => 'Acme', 'slug' => 'acme']);
        $this->alice = $this->agent('alice');
        $this->bob   = $this->agent('bob');
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

    private function presence(ApiKey $k, string $heartbeat): void
    {
        AgentPresence::updateOrCreate(
            ['agent_id' => $k->id],
            ['status' => 'available', 'available_since' => now(), 'last_heartbeat' => $heartbeat]
        );
    }

    private function openLink(?int $idleTtl = null): AgentLink
    {
        return AgentLink::create([
            'org_id'           => $this->org->id,
            'initiator_id'     => $this->alice->id,
            'target_id'        => $this->bob->id,
            'status'           => 'open',
            'opened_at'        => now(),
            'last_activity_at' => now()->subSeconds(3600), // idle 1h, past default 1800s
            'idle_ttl'         => $idleTtl,
        ]);
    }

    private function gc(): void
    {
        app(AgentLinkService::class)->expireStale();
    }

    // ── Item 6: heartbeat keepalive ─────────────────────────────────────────

    public function test_idle_link_stays_open_while_both_parties_online(): void
    {
        $link = $this->openLink();
        $this->presence($this->alice, now()->subSeconds(30)); // fresh (< 120s)
        $this->presence($this->bob, now()->subSeconds(30));

        $this->gc();

        $this->assertSame('open', $link->fresh()->status, 'both online → keepalive, not idle-closed');
    }

    public function test_idle_link_closes_when_one_party_goes_stale(): void
    {
        $link = $this->openLink();
        $this->presence($this->alice, now()->subSeconds(30));    // fresh
        $this->presence($this->bob, now()->subSeconds(600));     // stale (> 120s)

        $this->gc();

        $closed = $link->fresh();
        $this->assertSame('closed', $closed->status);
        $this->assertSame('idle_timeout', $closed->close_reason);
    }

    // ── Item 7: per-link idle_ttl ───────────────────────────────────────────

    public function test_custom_idle_ttl_keeps_link_open_past_global_default(): void
    {
        // Idle 1h, but this link declared a 2h idle_ttl → not yet idle.
        $link = $this->openLink(7200);
        // No presence rows at all (both offline), so only the TTL protects it.
        $this->gc();
        $this->assertSame('open', $link->fresh()->status);
    }

    public function test_link_request_accepts_idle_ttl(): void
    {
        $this->postJson('/api/v1/agents/comms/open', [], $this->hdr($this->alice))->assertOk();
        $this->postJson('/api/v1/agents/comms/open', [], $this->hdr($this->bob))->assertOk();

        $this->postJson('/api/v1/agents/links', [
            'target'   => 'bob',
            'idle_ttl' => 600,
        ], $this->hdr($this->alice))
            ->assertCreated()
            ->assertJsonPath('link.idle_ttl', 600);

        // Out-of-range is rejected.
        $this->postJson('/api/v1/agents/links', ['target' => 'bob', 'idle_ttl' => 5], $this->hdr($this->alice))
            ->assertStatus(422);
    }

    // ── Item 8: transactional message-expiry ────────────────────────────────

    public function test_sending_on_a_link_closed_underneath_returns_409(): void
    {
        $this->postJson('/api/v1/agents/comms/open', [], $this->hdr($this->alice))->assertOk();
        $this->postJson('/api/v1/agents/comms/open', [], $this->hdr($this->bob))->assertOk();

        $link = AgentLink::create([
            'org_id'           => $this->org->id,
            'initiator_id'     => $this->alice->id,
            'target_id'        => $this->bob->id,
            'status'           => 'open',
            'opened_at'        => now(),
            'last_activity_at' => now(),
        ]);

        // Simulate the link being idle-closed between request and send.
        $link->update(['status' => 'closed', 'close_reason' => 'idle_timeout']);

        $this->postJson('/api/v1/agents/messages', [
            'link_id' => $link->id,
            'body'    => 'too late',
        ], $this->hdr($this->alice))
            ->assertStatus(409)
            ->assertJsonPath('code', 'link_not_open');
    }

    private function hdr(ApiKey $k): array
    {
        return ['Authorization' => 'Bearer ' . $k->key];
    }
}
