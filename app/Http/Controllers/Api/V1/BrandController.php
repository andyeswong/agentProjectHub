<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Brand;
use App\Models\Workspace;
use App\Services\BrandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Brands (F2). resolve() is the counterpart of personality_resolve: an agent
 * asks "what brand does this project follow?" and gets the merged tokens,
 * rules and asset pointers — no guessing, no semantic search.
 */
class BrandController extends Controller
{
    public function __construct(
        private BrandService $brands,
    ) {}

    // POST /api/v1/brands/resolve  {project_id?, workspace_id?, slug?}
    public function resolve(Request $request): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');
        $data   = $request->validate([
            'slug'         => 'nullable|string|max:100',
            'project_id'   => 'nullable|uuid',
            'workspace_id' => 'nullable|uuid',
        ]);

        $workspaceIds = $this->orgWorkspaceIds($apiKey);
        if (empty($workspaceIds)) {
            return $this->noWorkspaceError();
        }

        $brand = $this->brands->resolve(
            $workspaceIds,
            $data['slug'] ?? null,
            $data['project_id'] ?? null,
            $data['workspace_id'] ?? null,
        );

        if (! $brand) {
            return response()->json([
                'error' => 'not_found',
                'hint'  => 'No brand matched. Pass a slug, point the project at a brand (projects.brand_id), or mark one brand is_default in the workspace.',
            ], 404);
        }

        return response()->json([
            'brand' => $brand,
            '_meta' => [
                'layers'   => count($brand['layers']),
                'inherits' => $brand['inherits'],
                'hint'     => 'Apply `tokens` as CSS variables and obey `rules`. `assets` are pointers grouped by role — fetch raw_url only when you need the bytes.',
            ],
        ]);
    }

    // GET /api/v1/brands
    public function index(Request $request): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');
        $brands = Brand::whereIn('workspace_id', $this->orgWorkspaceIds($apiKey))
            ->orderBy('slug')
            ->get();

        return response()->json(['data' => $brands]);
    }

    // GET /api/v1/brands/{slug} — raw record + linked assets (unmerged).
    public function show(Request $request, string $slug): JsonResponse
    {
        $brand = Brand::whereIn('workspace_id', $this->orgWorkspaceIds($request->attributes->get('api_key')))
            ->where('slug', $slug)
            ->with('assets')
            ->first();

        if (! $brand) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json(['brand' => $brand]);
    }

    // POST /api/v1/brands — idempotent upsert on (workspace, slug).
    public function upsert(Request $request): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        $data = $request->validate([
            'slug'         => 'required|string|max:100',
            'name'         => 'nullable|string|max:120',
            'parent_slug'  => 'nullable|string|max:100',
            'tokens'       => 'nullable|array',
            'voice'        => 'nullable|array',
            'rules'        => 'nullable|array',
            'rules.*'      => 'string',
            'meta'         => 'nullable|array',
            'is_default'   => 'nullable|boolean',
            'status'       => 'nullable|in:draft,active',
            'workspace_id' => 'nullable|uuid',
        ]);

        $workspace = $this->targetWorkspace($apiKey, $data['workspace_id'] ?? null);
        if (! $workspace) {
            return $this->noWorkspaceError();
        }

        // parent_slug -> parent_id. Passing parent_slug: null DETACHES the
        // brand; omitting the key entirely leaves the parent alone.
        if ($request->exists('parent_slug')) {
            $slug = $data['parent_slug'] ?? null;

            if ($slug === null || $slug === '') {
                $data['parent_id'] = null;                       // disinherit
            } else {
                $parent = Brand::where('workspace_id', $workspace->id)->where('slug', $slug)->first();
                if (! $parent) {
                    return response()->json(['error' => 'parent_not_found', 'hint' => "No brand '{$slug}' in this workspace."], 422);
                }
                if ($parent->slug === $data['slug']) {
                    return response()->json(['error' => 'self_parent'], 422);
                }

                $existing = Brand::where('workspace_id', $workspace->id)->where('slug', $data['slug'])->first();
                if ($this->brands->wouldCycle($existing, $parent->id)) {
                    return response()->json([
                        'error' => 'inheritance_cycle',
                        'hint'  => "'{$slug}' already descends from '{$data['slug']}'.",
                    ], 422);
                }

                $data['parent_id'] = $parent->id;
            }
        }

        [$brand, $created] = $this->brands->upsert($data, $workspace->id, $apiKey);

        return response()->json(['status' => $created ? 'created' : 'updated', 'brand' => $brand], $created ? 201 : 200);
    }

    // POST /api/v1/brands/{id}/assets  {asset_id, role, is_primary?, sort_order?}
    public function attachAsset(Request $request, string $id): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');
        $data   = $request->validate([
            'asset_id'   => 'required|uuid',
            'role'       => 'required|in:logo,logo-mark,icon,hero-ref,moodboard,mockup,palette-ref',
            'is_primary' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $wsIds = $this->orgWorkspaceIds($apiKey);
        $brand = Brand::whereIn('workspace_id', $wsIds)->find($id);
        $asset = Asset::whereIn('workspace_id', $wsIds)->find($data['asset_id']);

        if (! $brand || ! $asset) {
            return response()->json(['error' => 'not_found', 'hint' => 'Brand or asset not in your org.'], 404);
        }

        $this->brands->attachAsset($brand, $asset, $data['role'], $data['is_primary'] ?? false, $data['sort_order'] ?? 0);

        return response()->json(['status' => 'attached', 'brand' => $brand->slug, 'asset_id' => $asset->id, 'role' => $data['role']]);
    }

    // DELETE /api/v1/brands/{brandId}/assets/{assetId}
    public function detachAsset(Request $request, string $brandId, string $assetId): JsonResponse
    {
        $wsIds = $this->orgWorkspaceIds($request->attributes->get('api_key'));
        $brand = Brand::whereIn('workspace_id', $wsIds)->find($brandId);
        $asset = Asset::whereIn('workspace_id', $wsIds)->find($assetId);

        if (! $brand || ! $asset) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $this->brands->detachAsset($brand, $asset);

        return response()->json(['status' => 'detached']);
    }

    // DELETE /api/v1/brands/{slug}
    public function destroy(Request $request, string $slug): JsonResponse
    {
        $brand = Brand::whereIn('workspace_id', $this->orgWorkspaceIds($request->attributes->get('api_key')))
            ->where('slug', $slug)
            ->first();

        if (! $brand) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $promoted = $this->brands->delete($brand);

        return response()->json([
            'status'           => 'deleted',
            'slug'             => $slug,
            'children_promoted' => $promoted,
            '_meta'            => [
                'hint' => 'Linked assets were NOT deleted — only the links. Child brands became roots.',
            ],
        ]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function orgWorkspaceIds($apiKey): array
    {
        return Workspace::where('org_id', $apiKey->org_id)->pluck('id')->all();
    }

    private function targetWorkspace($apiKey, ?string $workspaceId): ?Workspace
    {
        $q = Workspace::where('org_id', $apiKey->org_id);
        return $workspaceId ? $q->where('id', $workspaceId)->first() : $q->orderBy('name')->first();
    }

    private function noWorkspaceError(): JsonResponse
    {
        return response()->json(['error' => 'no_workspace'], 422);
    }
}
