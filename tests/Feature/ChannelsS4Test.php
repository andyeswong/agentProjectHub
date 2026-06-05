<?php

namespace Tests\Feature;

use App\Models\AgentLink;
use App\Models\AgentMessage;
use App\Models\ApiKey;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChannelsS4Test extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private ApiKey $alice;
    private ApiKey $bob;
    private AgentLink $link;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org   = Organization::create(['name' => 'Acme', 'slug' => 'acme']);
        $this->alice = $this->agent('alice');
        $this->bob   = $this->agent('bob');
        $this->link  = AgentLink::create([
            'org_id'           => $this->org->id,
            'initiator_id'     => $this->alice->id,
            'target_id'        => $this->bob->id,
            'status'           => 'open',
            'opened_at'        => now(),
            'last_activity_at' => now(),
        ]);
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

    // ── RPC ──────────────────────────────────────────────────────────────────

    public function test_rpc_returns_the_matching_response(): void
    {
        $cid = (string) Str::uuid();

        // Peer's response is already waiting under this correlation_id.
        AgentMessage::create([
            'org_id'         => $this->org->id,
            'link_id'        => $this->link->id,
            'from_id'        => $this->bob->id,
            'type'           => 'response',
            'correlation_id' => $cid,
            'body'           => 'deploy OK',
        ]);

        $this->postJson('/api/v1/agents/messages/rpc', [
            'link_id'        => $this->link->id,
            'body'           => 'run the TLS deploy',
            'correlation_id' => $cid,
            'timeout'        => 5,
        ], $this->hdr($this->alice))
            ->assertOk()
            ->assertJsonPath('status', 'responded')
            ->assertJsonPath('correlation_id', $cid)
            ->assertJsonPath('response.body', 'deploy OK')
            ->assertJsonPath('request.type', 'request');

        // The consumed response is marked read so it won't re-surface in the inbox.
        $this->assertNotNull(AgentMessage::where('correlation_id', $cid)->where('type', 'response')->first()->read_at);
    }

    public function test_rpc_times_out_when_no_response(): void
    {
        $this->postJson('/api/v1/agents/messages/rpc', [
            'link_id' => $this->link->id,
            'body'    => 'anybody there?',
            'timeout' => 1,
        ], $this->hdr($this->alice))
            ->assertOk()
            ->assertJsonPath('status', 'timeout')
            ->assertJsonPath('response', null);
    }

    public function test_rpc_on_closed_link_returns_409(): void
    {
        $this->link->update(['status' => 'closed']);

        $this->postJson('/api/v1/agents/messages/rpc', [
            'link_id' => $this->link->id,
            'body'    => 'hello',
            'timeout' => 1,
        ], $this->hdr($this->alice))
            ->assertStatus(409)
            ->assertJsonPath('code', 'link_not_open');
    }

    // ── History ──────────────────────────────────────────────────────────────

    public function test_link_history_is_paginated_newest_first(): void
    {
        foreach (range(1, 5) as $i) {
            $m = AgentMessage::create([
                'org_id'  => $this->org->id,
                'link_id' => $this->link->id,
                'from_id' => $i % 2 ? $this->alice->id : $this->bob->id,
                'type'    => 'message',
                'body'    => "msg {$i}",
            ]);
            $m->forceFill(['created_at' => now()->addSeconds($i)])->save();
        }

        $resp = $this->getJson("/api/v1/agents/links/{$this->link->id}/messages?limit=3", $this->hdr($this->alice))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.body', 'msg 5'); // newest first

        $before = $resp->json('_meta.next_before');
        $this->getJson("/api/v1/agents/links/{$this->link->id}/messages?limit=3&before={$before}", $this->hdr($this->alice))
            ->assertOk()
            ->assertJsonPath('data.0.body', 'msg 2');
    }

    public function test_history_denied_to_non_party(): void
    {
        $carol = $this->agent('carol');
        $this->getJson("/api/v1/agents/links/{$this->link->id}/messages", $this->hdr($carol))
            ->assertStatus(404)
            ->assertJsonPath('code', 'link_not_found');
    }
}
