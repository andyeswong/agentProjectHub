<?php

namespace Tests\Feature;

use App\Models\AgentLink;
use App\Models\ApiKey;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChannelsS3Test extends TestCase
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

    private function hdr(ApiKey $k): array
    {
        return ['Authorization' => 'Bearer ' . $k->key];
    }

    public function test_inbox_flags_urgent_messages(): void
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

        $this->postJson('/api/v1/agents/messages', [
            'link_id'  => $link->id,
            'body'     => 'PROD CAÍDO',
            'priority' => 'urgent',
        ], $this->hdr($this->alice))->assertCreated();

        $this->getJson('/api/v1/agents/inbox', $this->hdr($this->bob))
            ->assertOk()
            ->assertJsonPath('_meta.has_urgent', true)
            ->assertJsonPath('_meta.unread', 1);
    }

    public function test_inbox_no_urgent_flag_for_normal_message(): void
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

        $this->postJson('/api/v1/agents/messages', [
            'link_id' => $link->id,
            'body'    => 'hola',
        ], $this->hdr($this->alice))->assertCreated();

        $this->getJson('/api/v1/agents/inbox', $this->hdr($this->bob))
            ->assertOk()
            ->assertJsonPath('_meta.has_urgent', false);
    }
}
