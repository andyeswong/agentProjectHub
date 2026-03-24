<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Organization;
use App\Models\PilotToken;
use App\Services\ActivityEventService;
use App\Services\ApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(
        private ApiKeyService $apiKeyService,
        private ActivityEventService $events,
    ) {}

    // POST /api/v1/auth/register — public, agent self-registration
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_type'    => 'required|string',
            'pilot'          => 'required|string',
            'pilot_contact'  => 'nullable|string',
            'model'          => 'required|string',
            'model_provider' => 'required|string',
            'capabilities'   => 'nullable|array',
            'org_id'         => 'nullable|string',
            'metadata'       => 'nullable|array',
        ]);

        $org = $data['org_id']
            ? Organization::firstOrCreate(
                ['slug' => Str::slug($data['org_id'])],
                ['name' => $data['org_id']]
            )
            : Organization::create([
                'name' => "{$data['pilot']}'s Org",
                'slug' => Str::slug($data['pilot'] . '-' . Str::random(6)),
            ]);

        $apiKey = $this->apiKeyService->create($org, $data);

        $this->events->record('agent.registered', 'api_key', $apiKey->id, $apiKey, [
            'model'       => $apiKey->model,
            'client_type' => $apiKey->client_type,
        ], $request->ip());

        return response()->json([
            'status'        => 'registered',
            'api_key'       => $apiKey->key,
            'org_id'        => $org->slug,
            'permissions'   => $apiKey->permissions,
            'rate_limit'    => $apiKey->rate_limit,
            'registered_at' => $apiKey->created_at,
        ], 201);
    }

    // POST /api/v1/auth/token — manual key generation
    public function token(Request $request): JsonResponse
    {
        $data = $request->validate([
            'org_id'     => 'required|string',
            'model'      => 'nullable|string',
            'owner_type' => 'nullable|in:agent,human',
            'name'       => 'nullable|string',
        ]);

        $org = Organization::where('slug', Str::slug($data['org_id']))->firstOrFail();
        $apiKey = $this->apiKeyService->create($org, array_merge($data, [
            'pilot' => $data['name'] ?? null,
        ]));

        return response()->json([
            'api_key'    => $apiKey->key,
            'org_id'     => $org->slug,
            'permissions' => $apiKey->permissions,
            'rate_limit' => $apiKey->rate_limit,
        ], 201);
    }

    // GET /api/v1/auth/me — agent introspection
    public function me(Request $request): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        return response()->json([
            'api_key_id'      => $apiKey->id,
            'key_prefix'      => Str::substr($apiKey->key, 0, 20) . '...',
            'org_id'          => $apiKey->organization->slug,
            'model'           => $apiKey->model,
            'model_provider'  => $apiKey->model_provider,
            'client_type'     => $apiKey->client_type,
            'pilot'           => $apiKey->pilot,
            'permissions'     => $apiKey->permissions,
            'rate_limit'      => $apiKey->rate_limit,
            'last_active_at'  => $apiKey->last_active_at,
            'registered_at'   => $apiKey->created_at,
        ]);
    }

    // POST /api/v1/auth/pilot-token — agent generates a token for their human
    public function pilotToken(Request $request): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        $rawToken = 'plt_' . Str::random(40);

        PilotToken::create([
            'api_key_id' => $apiKey->id,
            'token'      => hash('sha256', $rawToken),
            'pilot_name' => $apiKey->pilot ?? 'Unknown',
            'expires_at' => now()->addMinutes(15),
        ]);

        return response()->json([
            'pilot_token' => $rawToken,
            'expires_in'  => 900,
            'pilot'       => $apiKey->pilot,
        ]);
    }

    // POST /api/v1/auth/pilot-login — human logs in with pilot token
    public function pilotLogin(Request $request): JsonResponse
    {
        $data = $request->validate(['pilot_token' => 'required|string']);

        $hashed = hash('sha256', $data['pilot_token']);
        $pilotToken = PilotToken::where('token', $hashed)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->with('apiKey.organization')
            ->first();

        if (!$pilotToken) {
            return response()->json(['error' => 'Invalid or expired pilot token'], 401);
        }

        $pilotToken->update(['used_at' => now()]);

        $apiKey = $pilotToken->apiKey;

        $this->events->record('pilot.login', 'api_key', $apiKey->id, $apiKey, [
            'pilot' => $pilotToken->pilot_name,
        ], $request->ip());

        // Generate a session token for the human (reuse pilot token mechanism)
        $sessionToken = 'sess_' . Str::random(60);

        // Store session as a new pilot token with longer expiry
        PilotToken::create([
            'api_key_id' => $apiKey->id,
            'token'      => hash('sha256', $sessionToken),
            'pilot_name' => $pilotToken->pilot_name,
            'expires_at' => now()->addHours(8),
        ]);

        return response()->json([
            'session_token' => $sessionToken,
            'pilot'         => $pilotToken->pilot_name,
            'agent' => [
                'id'          => $apiKey->id,
                'model'       => $apiKey->model,
                'client_type' => $apiKey->client_type,
                'permissions' => $apiKey->permissions,
            ],
            'org' => [
                'id'   => $apiKey->organization->id,
                'name' => $apiKey->organization->name,
                'slug' => $apiKey->organization->slug,
            ],
        ]);
    }
}
