<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Workspace;
use App\Services\AssetService;
use App\Services\EmbeddingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Assets (F1) — independent binary store. Upload (base64), list, semantic
 * search, fetch metadata, stream raw bytes, delete. Org-scoped via the calling
 * api_key, same pattern as MemoryController.
 */
class AssetController extends Controller
{
    /** ~8 MB cap on a single base64 upload (fits logos/screenshots comfortably). */
    private const MAX_BYTES = 8 * 1024 * 1024;

    public function __construct(
        private AssetService $assets,
        private EmbeddingService $embedder,
    ) {}

    // GET /api/v1/assets — list assets across the org (optional workspace filter).
    public function index(Request $request): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');
        [$workspaceIds, $scopeLabel] = $this->resolveWorkspaceIds($apiKey, $request->query('workspace_id'));

        if (empty($workspaceIds)) {
            return $this->noWorkspaceError();
        }

        $query = Asset::whereIn('workspace_id', $workspaceIds);

        if ($request->filled('kind')) {
            $query->whereIn('kind', explode(',', $request->kind));
        }
        if ($request->filled('tags')) {
            foreach (explode(',', $request->tags) as $tag) {
                $query->whereJsonContains('tags', trim($tag));
            }
        }
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(fn ($sq) =>
                $sq->where('filename', 'like', "%{$q}%")
                   ->orWhere('description', 'like', "%{$q}%")
            );
        }

        $assets = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('limit', 50));

        return response()->json([
            'data'  => $assets->map(fn (Asset $a) => $a->toPublicArray())->values(),
            '_meta' => [
                'scope'         => $scopeLabel,
                'workspace_ids' => $workspaceIds,
                'returned'      => $assets->count(),
                'hint'          => 'POST /api/v1/assets/search for semantic search. GET /api/v1/assets/{id}/raw for bytes.',
            ],
        ]);
    }

    // POST /api/v1/assets — upload one asset (base64 in `data`).
    public function store(Request $request): JsonResponse
    {
        $apiKey    = $request->attributes->get('api_key');
        $workspace = $this->resolveTargetWorkspace($apiKey, $request->input('workspace_id'));

        if (!$workspace) {
            return $this->noWorkspaceError();
        }

        $data = $request->validate([
            'workspace_id' => 'nullable|uuid',
            'data'         => 'required|string',            // base64
            'filename'     => 'required|string|max:255',
            'mime_type'    => 'nullable|string|max:120',
            'kind'         => 'nullable|in:logo,logo-mark,icon,screenshot,reference,moodboard,mockup,other',
            'description'  => 'nullable|string',
            'tags'         => 'nullable|array',
            'tags.*'       => 'string|max:50',
            'brand_hint'   => 'nullable|uuid',
            'storage_disk' => 'nullable|string|max:60',
        ]);

        // Guard payload size before decoding the whole thing into memory.
        if (strlen($data['data']) > self::MAX_BYTES * 1.4) {
            return response()->json([
                'error' => 'payload_too_large',
                'hint'  => 'Single asset upload capped at ~8MB. For larger files use a direct disk upload.',
            ], 413);
        }

        try {
            $asset = $this->assets->store($data, $workspace->id, $apiKey);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'invalid_data', 'hint' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'stored',
            'asset'  => $asset->toPublicArray(),
            '_meta'  => [
                'embedded'    => $asset->isEmbedded(),
                'embed_model' => $asset->isEmbedded() ? $this->embedder->model() : null,
                'hint'        => 'Link it to a brand with brand_attach_asset (F2), or find it via asset_search.',
            ],
        ], 201);
    }

    // POST /api/v1/assets/search — semantic search over descriptions.
    public function search(Request $request): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');
        $data   = $request->validate([
            'q'            => 'required|string',
            'limit'        => 'nullable|integer|min:1|max:100',
            'workspace_id' => 'nullable|uuid',
        ]);

        [$workspaceIds] = $this->resolveWorkspaceIds($apiKey, $data['workspace_id'] ?? null);
        if (empty($workspaceIds)) {
            return $this->noWorkspaceError();
        }

        $res = $this->assets->search($data['q'], $workspaceIds, $data['limit'] ?? 20);

        return response()->json([
            'results' => $res['results']->map(fn ($r) => [
                'asset' => $r['asset']->toPublicArray(),
                'score' => $r['score'],
            ])->values(),
            '_meta' => [
                'embedded'    => $res['embedded'],
                'fallback'    => $res['fallback'],
                'embed_model' => $this->embedder->model(),
            ],
        ]);
    }

    // GET /api/v1/assets/{id} — metadata.
    public function show(Request $request, string $id): JsonResponse
    {
        $asset = $this->findScoped($request, $id);
        if (!$asset) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json(['asset' => $asset->toPublicArray()]);
    }

    // GET /api/v1/assets/{id}/raw — stream the bytes.
    public function raw(Request $request, string $id): StreamedResponse|JsonResponse
    {
        $asset = $this->findScoped($request, $id);
        if (!$asset) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $disk = \Illuminate\Support\Facades\Storage::disk($asset->storage_disk);
        if (!$disk->exists($asset->storage_key)) {
            return response()->json(['error' => 'file_missing'], 404);
        }

        return $disk->response($asset->storage_key, $asset->filename, [
            'Content-Type'  => $asset->mime_type,
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    // DELETE /api/v1/assets/{id}
    public function destroy(Request $request, string $id): JsonResponse
    {
        $asset = $this->findScoped($request, $id);
        if (!$asset) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $this->assets->delete($asset);

        return response()->json(['status' => 'deleted', 'id' => $id]);
    }

    // ── Helpers (org-scoping, mirrors MemoryController) ─────────────────────

    private function findScoped(Request $request, string $id): ?Asset
    {
        $apiKey = $request->attributes->get('api_key');
        [$workspaceIds] = $this->resolveWorkspaceIds($apiKey, null);

        return Asset::whereIn('workspace_id', $workspaceIds)->where('id', $id)->first();
    }

    /** @return array{0: array<string>, 1: ?string} */
    private function resolveWorkspaceIds($apiKey, ?string $workspaceId): array
    {
        if ($workspaceId) {
            $ws = Workspace::where('org_id', $apiKey->org_id)->where('id', $workspaceId)->first();
            return $ws ? [[$ws->id], $ws->name] : [[], null];
        }
        $ids = Workspace::where('org_id', $apiKey->org_id)->pluck('id')->all();
        return [$ids, 'org'];
    }

    private function resolveTargetWorkspace($apiKey, ?string $workspaceId): ?Workspace
    {
        $query = Workspace::where('org_id', $apiKey->org_id);
        if ($workspaceId) {
            return $query->where('id', $workspaceId)->first();
        }
        return $query->orderBy('name')->first();
    }

    private function noWorkspaceError(): JsonResponse
    {
        return response()->json([
            'error' => 'no_workspace',
            'hint'  => 'This org has no workspace, or the workspace_id is not in your org.',
        ], 422);
    }
}
