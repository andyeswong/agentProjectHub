<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
    // GET /api/v1/organizations
    public function index(Request $request): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');
        $org = $apiKey->organization()->with('workspaces')->first();

        return response()->json(['data' => [$org]]);
    }

    // GET /api/v1/organizations/:slug/workspaces
    public function workspaces(Request $request, string $slug): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        $org = Organization::where('slug', $slug)
            ->where('id', $apiKey->org_id)
            ->firstOrFail();

        return response()->json(['data' => $org->workspaces]);
    }

    // POST /api/v1/organizations/:slug/workspaces
    public function createWorkspace(Request $request, string $slug): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        $org = Organization::where('slug', $slug)
            ->where('id', $apiKey->org_id)
            ->firstOrFail();

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $workspace = Workspace::create([
            'org_id' => $org->id,
            'name'   => $data['name'],
            'slug'   => Str::slug($data['name']),
        ]);

        return response()->json($workspace, 201);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['error' => 'Organizations are created via /auth/register.'], 405);
    }

    public function show(string $id): JsonResponse { return response()->json([], 404); }
    public function update(Request $request, string $id): JsonResponse { return response()->json([], 404); }
    public function destroy(string $id): JsonResponse { return response()->json([], 404); }
}
