<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgentChannelsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private ApiKey $alice;
    private ApiKey $bob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org   = Organization::create(['name' => 'Acme', 'slug' => 'acme']);
        $this->alice = $this->makeAgent('alice');
        $this->bob   = $this->makeAgent('bob');
    }

    private function makeAgent(string $handle, array $perms = ['read', 'write', 'comms']): ApiKey
    {
        return ApiKey::create([
            'key'         => 'sk_test_' . $handle . '_' . Str::random(8),
            'org_id'      => $this->org->id,
            'owner_type'  => 'agent',
            'model'       => $handle,
            'client_type' => 'api',
            'handle'      => $handle,
            'permissions' => $perms,
            'rate_limit'  => 120,
        ]);
    }

    private function hdr(ApiKey $k): array
    {
        return ['Authorization' => 'Bearer ' . $k->key];
    }

    public function test_full_handshake_and_message_flow(): void
    {
        // Both pilots open comms
        $this->postJson('/api/v1/agents/comms/open', [], $this->hdr($this->alice))->assertOk();
        $this->postJson('/api/v1/agents/comms/open', [], $this->hdr($this->bob))->assertOk();

        // Alice requests a link to bob
        $req = $this->postJson('/api/v1/agents/links', [
            'target' => 'bob',
            'intent' => 'ejecuta el deploy de TLS',
        ], $this->hdr($this->alice))->assertCreated()->assertJsonPath('status', 'pending');
        $linkId = $req->json('link.id');

        // Bob sees it pending and accepts
        $this->getJson('/api/v1/agents/links/pending', $this->hdr($this->bob))
            ->assertOk()->assertJsonCount(1, 'data');

        $this->postJson("/api/v1/agents/links/{$linkId}/accept", [], $this->hdr($this->bob))
            ->assertOk()->assertJsonPath('status', 'open');

        // Alice sends a message with an entity ref
        $this->postJson('/api/v1/agents/messages', [
            'link_id' => $linkId,
            'body'    => 'arranca cuando puedas',
            'refs'    => [['type' => 'project', 'id' => 'tls-123']],
        ], $this->hdr($this->alice))->assertCreated();

        // Bob's inbox shows it
        $inbox = $this->getJson('/api/v1/agents/inbox', $this->hdr($this->bob))
            ->assertOk()->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', 'arranca cuando puedas');
        $msgId = $inbox->json('messages.0.id');

        // Bob acks -> inbox empty
        $this->postJson('/api/v1/agents/inbox/ack', ['ids' => [$msgId]], $this->hdr($this->bob))
            ->assertOk()->assertJsonPath('acked', 1);
        $this->getJson('/api/v1/agents/inbox', $this->hdr($this->bob))
            ->assertOk()->assertJsonCount(0, 'messages');

        // Either party closes
        $this->postJson("/api/v1/agents/links/{$linkId}/close", [], $this->hdr($this->alice))
            ->assertOk()->assertJsonPath('status', 'closed');
    }

    public function test_cannot_link_to_unavailable_target(): void
    {
        $this->postJson('/api/v1/agents/comms/open', [], $this->hdr($this->alice))->assertOk();
        // bob has NOT opened comms
        $this->postJson('/api/v1/agents/links', ['target' => 'bob'], $this->hdr($this->alice))
            ->assertStatus(409)->assertJsonPath('code', 'target_unavailable');
    }

    public function test_cannot_request_link_before_opening_own_comms(): void
    {
        $this->postJson('/api/v1/agents/comms/open', [], $this->hdr($this->bob))->assertOk();
        $this->postJson('/api/v1/agents/links', ['target' => 'bob'], $this->hdr($this->alice))
            ->assertStatus(409)->assertJsonPath('code', 'initiator_unavailable');
    }

    public function test_messaging_blocked_without_open_link(): void
    {
        $this->postJson('/api/v1/agents/comms/open', [], $this->hdr($this->alice))->assertOk();
        $this->postJson('/api/v1/agents/comms/open', [], $this->hdr($this->bob))->assertOk();

        $linkId = $this->postJson('/api/v1/agents/links', ['target' => 'bob'], $this->hdr($this->alice))
            ->json('link.id'); // pending, not accepted

        $this->postJson('/api/v1/agents/messages', [
            'link_id' => $linkId,
            'body'    => 'hola',
        ], $this->hdr($this->alice))->assertStatus(409)->assertJsonPath('code', 'link_not_open');
    }

    public function test_third_party_cannot_accept(): void
    {
        $this->postJson('/api/v1/agents/comms/open', [], $this->hdr($this->alice))->assertOk();
        $this->postJson('/api/v1/agents/comms/open', [], $this->hdr($this->bob))->assertOk();
        $carol = $this->makeAgent('carol');
        $this->postJson('/api/v1/agents/comms/open', [], $this->hdr($carol))->assertOk();

        $linkId = $this->postJson('/api/v1/agents/links', ['target' => 'bob'], $this->hdr($this->alice))->json('link.id');

        $this->postJson("/api/v1/agents/links/{$linkId}/accept", [], $this->hdr($carol))
            ->assertStatus(404)->assertJsonPath('code', 'link_not_found');
    }

    public function test_denies_agent_without_comms_capability(): void
    {
        $mute = $this->makeAgent('mute', ['read', 'write']); // no 'comms'
        $this->postJson('/api/v1/agents/comms/open', [], $this->hdr($mute))
            ->assertStatus(403)->assertJsonPath('code', 'forbidden');
    }
}
