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
            'org_name'       => 'nullable|string',
            'metadata'       => 'nullable|array',
        ]);

        $orgId   = $data['org_id']   ?? null;
        $orgName = $data['org_name'] ?? null;

        if ($orgId) {
            // Join an existing org by slug (invite flow)
            $org = Organization::firstOrCreate(
                ['slug' => Str::slug($orgId)],
                ['name' => $orgId]
            );
        } elseif ($orgName) {
            // Create a new org with the given name (register flow)
            $org = Organization::firstOrCreate(
                ['slug' => Str::slug($orgName)],
                ['name' => $orgName]
            );
        } else {
            // Fallback: auto-create org from pilot name
            $org = Organization::create([
                'name' => "{$data['pilot']}'s Org",
                'slug' => Str::slug($data['pilot'] . '-' . Str::random(6)),
            ]);
        }

        $apiKey = $this->apiKeyService->create($org, $data);

        $this->events->record('agent.registered', 'api_key', $apiKey->id, $apiKey, [
            'model'       => $apiKey->model,
            'client_type' => $apiKey->client_type,
        ], $request->ip());

        $baseUrl = url('/api/v1');

        return response()->json([
            'status'        => 'registered',
            'api_key'       => $apiKey->key,
            'org_id'        => $org->slug,
            'permissions'   => $apiKey->permissions,
            'rate_limit'    => $apiKey->rate_limit,
            'registered_at' => $apiKey->created_at,

            // ── Agent onboarding instructions ────────────────────────────
            'agent_instructions' => [
                'authentication' => [
                    'description' => 'Include your api_key in every subsequent request as a Bearer token.',
                    'header'      => 'Authorization: Bearer ' . $apiKey->key,
                ],
                'next_steps' => [
                    [
                        'step'        => 1,
                        'action'      => 'Create a workspace',
                        'method'      => 'POST',
                        'endpoint'    => "{$baseUrl}/organizations/{$org->slug}/workspaces",
                        'body'        => ['name' => 'My Workspace'],
                        'description' => 'A workspace groups related projects. Save the returned workspace id.',
                    ],
                    [
                        'step'        => 2,
                        'action'      => 'Create a project',
                        'method'      => 'POST',
                        'endpoint'    => "{$baseUrl}/projects",
                        'body'        => ['workspace_id' => '<workspace_id from step 1>', 'name' => 'My Project'],
                        'description' => 'Projects hold tasks. Save the returned project id.',
                    ],
                    [
                        'step'        => 3,
                        'action'      => 'Create tasks',
                        'method'      => 'POST',
                        'endpoint'    => "{$baseUrl}/projects/<project_id>/tasks/batch",
                        'body'        => ['tasks' => [['title' => 'First task', 'status' => 'todo', 'priority' => 'medium']]],
                        'description' => 'Use batch to create multiple tasks at once (max 50). Or POST /projects/<id>/tasks for a single task.',
                    ],
                    [
                        'step'        => 4,
                        'action'      => 'Poll for events',
                        'method'      => 'GET',
                        'endpoint'    => "{$baseUrl}/events?since=<ISO8601_timestamp>",
                        'description' => 'Poll this endpoint periodically to stay in sync with all changes made by humans or other agents. Store the latest event timestamp and use it as the ?since= value on the next poll.',
                    ],
                    [
                        'step'        => 5,
                        'action'      => 'Give your human pilot dashboard access (optional)',
                        'method'      => 'POST',
                        'endpoint'    => "{$baseUrl}/auth/pilot-token",
                        'description' => 'Call this with your API key to get a one-time pilot_token (valid 15 min). Share it with your human operator so they can log into the dashboard at ' . url('/login') . '.',
                    ],
                ],
                'tool_registration' => [
                    'description'   => 'To register ProjectHub as a callable tool in your system prompt or tool definition, use the following template.',
                    'system_prompt_snippet' => implode("\n", [
                        'You have access to ProjectHub LLM, a project management system.',
                        'Base URL: ' . $baseUrl,
                        'Auth header: Authorization: Bearer ' . $apiKey->key,
                        'Your org_id: ' . $org->slug,
                        '',
                        'Key operations:',
                        '  - List projects:        GET   ' . $baseUrl . '/projects',
                        '  - Create project:       POST  ' . $baseUrl . '/projects',
                        '  - List tasks:           GET   ' . $baseUrl . '/projects/{project_id}/tasks',
                        '  - List + archived:      GET   ' . $baseUrl . '/projects/{project_id}/tasks?include_archived=true',
                        '  - Create tasks (batch): POST  ' . $baseUrl . '/projects/{project_id}/tasks/batch',
                        '  - Update task:          PATCH ' . $baseUrl . '/tasks/{task_id}   body: {"status":"in_progress|done|blocked", ...}',
                        '  - Move task to project: PATCH ' . $baseUrl . '/tasks/{task_id}   body: {"project_id":"<destination_project_uuid>"}',
                        '  - Archive task:         POST  ' . $baseUrl . '/tasks/{task_id}/archive   body: {"reason":"optional reason"}',
                        '  - Unarchive task:       POST  ' . $baseUrl . '/tasks/{task_id}/unarchive',
                        '  - Add comment:          POST  ' . $baseUrl . '/tasks/{task_id}/comments',
                        '  - Poll events:          GET   ' . $baseUrl . '/events?since={ISO8601}',
                        '  - Full schema:          GET   ' . $baseUrl . '/schema',
                    ]),
                    'tool_definition_example' => [
                        'name'        => 'project_hub',
                        'description' => 'Manage projects and tasks in ProjectHub. Use this tool to create, list, and update projects and tasks, post comments, and poll for activity events.',
                        'parameters'  => [
                            'method'   => ['type' => 'string', 'enum' => ['GET', 'POST', 'PATCH']],
                            'endpoint' => ['type' => 'string', 'description' => 'Full URL, e.g. ' . $baseUrl . '/projects'],
                            'body'     => ['type' => 'object', 'description' => 'Request body for POST/PATCH (optional for GET)'],
                        ],
                    ],
                ],
                'schema_url' => "{$baseUrl}/schema",
            ],
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
