<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthKeyHashTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_key_is_stored_hashed_and_authenticates(): void
    {
        $resp = $this->postJson('/api/v1/auth/register', [
            'client_type'    => 'claude-code',
            'pilot'          => 'Andres',
            'model'          => 'tester',
            'model_provider' => 'anthropic',
            'org_id'         => 'acme',
        ])->assertCreated();

        $rawKey = $resp->json('api_key');
        $this->assertNotEmpty($rawKey);
        $this->assertStringStartsWith('sk_proj_', $rawKey);

        // The DB must NOT contain the plaintext key — only its hash + a prefix.
        $row = DB::table('api_keys')->first();
        $this->assertNull($row->key, 'plaintext key must not be stored');
        $this->assertSame(hash('sha256', $rawKey), $row->key_hash);
        $this->assertSame(substr($rawKey, 0, 20), $row->key_prefix);

        // The raw key still authenticates (resolved via its hash).
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer ' . $rawKey])
            ->assertOk()
            ->assertJsonPath('model', 'tester');

        // A wrong key does not authenticate.
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer sk_proj_acme_tester_deadbeef'])
            ->assertStatus(401);
    }
}
