<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\Organization;
use Illuminate\Support\Str;

class ApiKeyService
{
    public function generate(Organization $org, string $modelSlug): string
    {
        $uuid = Str::substr(Str::uuid()->toString(), 0, 8);
        return "sk_proj_{$org->slug}_{$modelSlug}_{$uuid}";
    }

    public function resolve(string $rawKey): ?ApiKey
    {
        return ApiKey::where('key', $rawKey)
            ->whereNull('revoked_at')
            ->first();
    }

    public function create(Organization $org, array $data): ApiKey
    {
        $modelSlug = Str::slug($data['model'] ?? 'custom', '-');
        $key = $this->generate($org, $modelSlug);

        return ApiKey::create([
            'key'               => $key,
            'org_id'            => $org->id,
            'workspace_id'      => $data['workspace_id'] ?? null,
            'owner_type'        => $data['owner_type'] ?? 'agent',
            'model'             => $data['model'] ?? null,
            'model_provider'    => $data['model_provider'] ?? null,
            'client_type'       => $data['client_type'] ?? 'custom',
            'pilot'             => $data['pilot'] ?? null,
            'pilot_contact'     => $data['pilot_contact'] ?? null,
            'permissions'       => $data['capabilities'] ?? ['read', 'write', 'comment'],
            'rate_limit'        => $data['rate_limit'] ?? 120,
            'system_prompt_hash' => $data['metadata']['system_prompt_hash'] ?? null,
            'metadata'          => $data['metadata'] ?? null,
        ]);
    }
}
