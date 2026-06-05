<?php

namespace Tests\Feature;

use App\Models\ActivityEvent;
use App\Models\AgentMemory;
use App\Models\ApiKey;
use App\Models\Organization;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityS1Test extends TestCase
{
    use RefreshDatabase;

    private function key(Organization $org, string $handle, array $perms = ['read', 'write']): ApiKey
    {
        $raw = 'sk_test_' . $handle . '_' . Str::random(8);
        return ApiKey::create([
            'key'         => $raw,
            'key_hash'    => hash('sha256', $raw),
            'org_id'      => $org->id,
            'owner_type'  => 'agent',
            'model'       => $handle,
            'client_type' => 'api',
            'handle'      => $handle,
            'permissions' => $perms,
            'rate_limit'  => 120,
        ]);
    }

    // ── Audit query endpoint ────────────────────────────────────────────────

    public function test_audit_query_filters_by_actor_event_type_and_date(): void
    {
        $org   = Organization::create(['name' => 'Acme', 'slug' => 'acme']);
        $alice = $this->key($org, 'alice');
        $bob   = $this->key($org, 'bob');

        $ev = function (ApiKey $by, string $type, string $when) {
            $e = ActivityEvent::create([
                'event_type'       => $type,
                'entity_type'      => 'memory',
                'entity_id'        => (string) Str::uuid(),
                'actor_api_key_id' => $by->id,
                'actor_model'      => $by->model,
                'payload'          => [],
                'ip_address'       => '1.2.3.4',
            ]);
            $e->created_at = $when; // not fillable; set explicitly
            $e->save();
            return $e;
        };

        $ev($alice, 'secret.revealed', '2026-06-01 10:00:00');
        $ev($alice, 'secret.reveal_denied', '2026-06-02 10:00:00');
        $ev($bob,   'secret.revealed', '2026-06-03 10:00:00');

        // Filter by actor
        $this->getJson('/api/v1/events?actor=' . $alice->id, $this->hdr($alice))
            ->assertOk()->assertJsonCount(2, 'data');

        // Filter by event_type (comma list)
        $this->getJson('/api/v1/events?event_type=secret.revealed', $this->hdr($alice))
            ->assertOk()->assertJsonCount(2, 'data');

        // Filter by date range
        $range = http_build_query(['from' => '2026-06-02 00:00:00', 'to' => '2026-06-02 23:59:59']);
        $this->getJson('/api/v1/events?' . $range, $this->hdr($alice))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'secret.reveal_denied')
            ->assertJsonPath('data.0.ip', '1.2.3.4');
    }

    // ── Self-revoke ─────────────────────────────────────────────────────────

    public function test_agent_can_revoke_own_key_and_it_stops_authenticating(): void
    {
        $org = Organization::create(['name' => 'Acme', 'slug' => 'acme']);
        $k   = $this->key($org, 'doomed');

        $this->postJson('/api/v1/auth/revoke', ['reason' => 'compromised'], $this->hdr($k))
            ->assertOk()->assertJsonPath('status', 'revoked');

        $this->assertDatabaseHas('activity_events', [
            'event_type'       => 'agent.key_revoked',
            'actor_api_key_id' => $k->id,
        ]);

        // The revoked key no longer authenticates.
        $this->getJson('/api/v1/auth/me', $this->hdr($k))->assertStatus(401);
    }

    // ── Granular rate limit on sensitive reads ──────────────────────────────

    public function test_sensitive_reads_are_rate_limited_independently(): void
    {
        config(['security.sensitive_rate_limit' => 2]);

        $org = Organization::create(['name' => 'Acme', 'slug' => 'acme']);
        $ws  = Workspace::create(['org_id' => $org->id, 'name' => 'Main', 'slug' => 'main']);
        $k   = $this->key($org, 'reader', ['read', 'reveal_secrets']);

        $mem = AgentMemory::create([
            'workspace_id' => $ws->id,
            'created_by'   => $k->id,
            'type'         => 'note',
            'label'        => 'n',
            'content'      => 'hi',
            'is_sensitive' => false,
        ]);

        $url = "/api/v1/memory/{$mem->id}";
        $this->getJson($url, $this->hdr($k))->assertOk();
        $this->getJson($url, $this->hdr($k))->assertOk();
        // 3rd within the window trips the tighter sensitive bucket.
        $this->getJson($url, $this->hdr($k))->assertStatus(429);
    }

    private function hdr(ApiKey $k): array
    {
        return ['Authorization' => 'Bearer ' . $k->key];
    }
}
