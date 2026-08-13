<?php

namespace Tests\Feature;

use App\Models\AgentMemory;
use App\Models\ApiKey;
use App\Models\Organization;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Assigning a memory_key to an EXISTING memory.
 *
 * Before this, PUT /api/v1/memory/{id} silently dropped `key`: it answered
 * {"status":"updated"} and bumped updated_at while memory_key stayed null.
 * Memories created without a key were therefore unreachable by
 * GET /memory?key= and by memory_list(key=...) forever.
 */
class MemoryKeyAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Workspace $ws;
    private ApiKey $apiKey;
    private string $raw;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['id' => (string) Str::uuid(), 'name' => 'Acme', 'slug' => 'acme-' . Str::random(6)]);
        $this->ws  = Workspace::create(['id' => (string) Str::uuid(), 'org_id' => $this->org->id, 'name' => 'main', 'slug' => 'main']);

        $this->raw    = 'sk_test_keyassign_' . Str::random(8);
        $this->apiKey = ApiKey::create([
            'key'         => $this->raw,
            'key_hash'    => hash('sha256', $this->raw),
            'org_id'      => $this->org->id,
            'owner_type'  => 'agent',
            'model'       => 'tester',
            'client_type' => 'api',
            'handle'      => 'tester',
            'permissions' => ['read', 'write'],
            'rate_limit'  => 120,
        ]);
    }

    private function memory(?string $key = null): AgentMemory
    {
        return AgentMemory::create([
            'workspace_id' => $this->ws->id,
            'created_by'   => $this->apiKey->id,
            'memory_key'   => $key,
            'type'         => 'fact',
            'label'        => 'Blog Posts - Topics Published',
            'content'      => 'the list of published posts',
        ]);
    }

    private function auth(): array
    {
        return ['Authorization' => 'Bearer ' . $this->raw];
    }

    public function test_assigns_a_key_to_a_memory_that_never_had_one(): void
    {
        $mem = $this->memory();
        $this->assertNull($mem->memory_key);

        $this->putJson("/api/v1/memory/{$mem->id}", ['key' => 'blog-posts'], $this->auth())
            ->assertStatus(200)
            ->assertJsonPath('memory.memory_key', 'blog-posts');

        $this->assertSame('blog-posts', $mem->fresh()->memory_key);
    }

    public function test_accepts_memory_key_as_an_alias(): void
    {
        // The 409 hints emitted by update() tell clients to PUT {"memory_key": ...},
        // so that spelling has to work too.
        $mem = $this->memory();

        $this->putJson("/api/v1/memory/{$mem->id}", ['memory_key' => 'blog-architecture'], $this->auth())
            ->assertStatus(200);

        $this->assertSame('blog-architecture', $mem->fresh()->memory_key);
    }

    public function test_clears_a_key_when_passed_null(): void
    {
        $mem = $this->memory('some-key');

        $this->putJson("/api/v1/memory/{$mem->id}", ['key' => null], $this->auth())
            ->assertStatus(200);

        $this->assertNull($mem->fresh()->memory_key);
    }

    public function test_rejects_a_key_already_taken_in_the_same_workspace(): void
    {
        $taken = $this->memory('blog-posts');
        $mem   = $this->memory();

        $this->putJson("/api/v1/memory/{$mem->id}", ['key' => 'blog-posts'], $this->auth())
            ->assertStatus(409)
            ->assertJsonPath('code', 'memory_key_conflict')
            ->assertJsonPath('conflicting_memory.id', $taken->id);

        $this->assertNull($mem->fresh()->memory_key, 'the memory must keep its previous key on conflict');
    }

    public function test_reassigning_the_same_key_to_itself_is_a_noop_not_a_conflict(): void
    {
        $mem = $this->memory('blog-posts');

        $this->putJson("/api/v1/memory/{$mem->id}", ['key' => 'blog-posts'], $this->auth())
            ->assertStatus(200);

        $this->assertSame('blog-posts', $mem->fresh()->memory_key);
    }

    public function test_updating_other_fields_does_not_touch_the_key(): void
    {
        $mem = $this->memory('blog-posts');

        $this->putJson("/api/v1/memory/{$mem->id}", ['label' => 'Renamed'], $this->auth())
            ->assertStatus(200);

        $fresh = $mem->fresh();
        $this->assertSame('Renamed', $fresh->label);
        $this->assertSame('blog-posts', $fresh->memory_key);
    }
}
