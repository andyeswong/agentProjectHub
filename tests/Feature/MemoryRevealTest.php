<?php

namespace Tests\Feature;

use App\Models\AgentMemory;
use App\Models\ApiKey;
use App\Models\Organization;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemoryRevealTest extends TestCase
{
    use RefreshDatabase;

    private function key(Organization $org, string $handle, array $perms): ApiKey
    {
        return ApiKey::create([
            'key'         => 'sk_test_' . $handle . '_' . Str::random(8),
            'org_id'      => $org->id,
            'owner_type'  => 'agent',
            'model'       => $handle,
            'client_type' => 'api',
            'handle'      => $handle,
            'permissions' => $perms,
            'rate_limit'  => 120,
        ]);
    }

    private function sensitiveMemory(Organization $org, Workspace $ws, ApiKey $by): AgentMemory
    {
        return AgentMemory::create([
            'workspace_id' => $ws->id,
            'created_by'   => $by->id,
            'type'         => 'credential',
            'label'        => 'Prod DB password',
            'content'      => 'the password is hunter2',
            'value'        => ['password' => 'hunter2'],
            'is_sensitive' => true,
        ]);
    }

    public function test_key_with_reveal_secrets_sees_unmasked_value(): void
    {
        $org = Organization::create(['name' => 'Acme', 'slug' => 'acme']);
        $ws  = Workspace::create(['org_id' => $org->id, 'name' => 'Main', 'slug' => 'main']);
        $priv = $this->key($org, 'priv', ['read', 'reveal_secrets']);
        $mem  = $this->sensitiveMemory($org, $ws, $priv);

        $this->getJson("/api/v1/memory/{$mem->id}", ['Authorization' => 'Bearer ' . $priv->key])
            ->assertOk()
            ->assertJsonPath('memory.content', 'the password is hunter2')
            ->assertJsonPath('memory.value.password', 'hunter2')
            ->assertJsonPath('_meta.revealed', true);
    }

    public function test_key_without_reveal_secrets_gets_masked(): void
    {
        $org = Organization::create(['name' => 'Acme', 'slug' => 'acme']);
        $ws  = Workspace::create(['org_id' => $org->id, 'name' => 'Main', 'slug' => 'main']);
        $owner = $this->key($org, 'owner', ['read', 'reveal_secrets']);
        $plain = $this->key($org, 'plain', ['read']); // no reveal_secrets
        $mem   = $this->sensitiveMemory($org, $ws, $owner);

        $resp = $this->getJson("/api/v1/memory/{$mem->id}", ['Authorization' => 'Bearer ' . $plain->key])
            ->assertOk()
            ->assertJsonPath('memory.content', '[sensitive — click Reveal to view]')
            ->assertJsonPath('_meta.revealed', false);

        $this->assertNotSame('hunter2', $resp->json('memory.value.password'));
    }

    public function test_non_sensitive_memory_is_always_returned(): void
    {
        $org = Organization::create(['name' => 'Beta', 'slug' => 'beta']);
        $ws  = Workspace::create(['org_id' => $org->id, 'name' => 'Main', 'slug' => 'main']);
        $plain = $this->key($org, 'plain', ['read']);
        $mem = AgentMemory::create([
            'workspace_id' => $ws->id,
            'created_by'   => $plain->id,
            'type'         => 'note',
            'label'        => 'Public note',
            'content'      => 'nothing secret here',
            'is_sensitive' => false,
        ]);

        $this->getJson("/api/v1/memory/{$mem->id}", ['Authorization' => 'Bearer ' . $plain->key])
            ->assertOk()
            ->assertJsonPath('memory.content', 'nothing secret here');
    }
}
