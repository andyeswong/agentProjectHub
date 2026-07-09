<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Workspace;
use App\Services\AssetService;
use App\Services\EmbeddingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Assets library in the pilot panel — browse, upload and delete the org's
 * binary assets (logos, screenshots, brand references). The visual companion
 * to the F1 API. Assets stand alone here; the Brand Board (F2) will reference
 * them.
 */
class AssetWebController extends Controller
{
    public function __construct(
        private AssetService $assets,
        private EmbeddingService $embedder,
    ) {}

    public function index(Request $request): Response
    {
        $apiKey     = $request->attributes->get('pilot_api_key');
        $org        = $apiKey->organization;
        $workspaces = Workspace::where('org_id', $org->id)->orderBy('name')->get();
        $allIds     = $workspaces->pluck('id')->all();

        $filterWorkspaceId = $request->input('workspace_id');
        $targetIds = $filterWorkspaceId && $workspaces->contains('id', $filterWorkspaceId)
            ? [$filterWorkspaceId]
            : $allIds;

        $q        = $request->input('q', '');
        $kind     = $request->input('kind', '');
        $semantic = $request->boolean('semantic');

        $assets = collect();
        $mode   = 'list';

        if ($q && $semantic) {
            $result = $this->assets->search($q, $targetIds, 60);
            $mode   = $result['embedded'] ? 'semantic' : 'keyword_fallback';
            $assets = $result['results']->map(fn ($r, $i) => [
                ...$this->present($r['asset']),
                '_score' => $r['score'],
                '_rank'  => $i + 1,
            ]);
        } else {
            $query = Asset::whereIn('workspace_id', $targetIds);
            if ($q) {
                $query->where(fn ($sq) =>
                    $sq->where('filename', 'like', "%{$q}%")
                       ->orWhere('description', 'like', "%{$q}%")
                );
                $mode = 'keyword';
            }
            if ($kind) {
                $query->where('kind', $kind);
            }
            $assets = $query->orderByDesc('created_at')->get()->map(fn ($a) => $this->present($a));
        }

        $countsByWorkspace = Asset::whereIn('workspace_id', $allIds)
            ->selectRaw('workspace_id, count(*) as count')
            ->groupBy('workspace_id')
            ->pluck('count', 'workspace_id');

        $stats = [
            'total'    => Asset::whereIn('workspace_id', $targetIds)->count(),
            'by_kind'  => Asset::whereIn('workspace_id', $targetIds)
                            ->selectRaw('kind, count(*) as count')
                            ->groupBy('kind')
                            ->pluck('count', 'kind'),
            'embedded' => Asset::whereIn('workspace_id', $targetIds)->whereNotNull('embedding')->count(),
        ];

        return Inertia::render('Assets/Index', [
            'assets'      => $assets->values(),
            'stats'       => $stats,
            'filters'     => ['q' => $q, 'kind' => $kind, 'semantic' => $semantic, 'workspace_id' => $filterWorkspaceId],
            'search_mode' => $mode,
            'embed_model' => $this->embedder->model(),
            'workspaces'  => $workspaces->map(fn ($w) => [
                'id'          => $w->id,
                'name'        => $w->name,
                'slug'        => $w->slug,
                'asset_count' => $countsByWorkspace[$w->id] ?? 0,
            ]),
            'active_workspace_id' => $filterWorkspaceId,
        ]);
    }

    // POST /assets — upload from the panel (base64 payload built client-side).
    public function store(Request $request): RedirectResponse
    {
        $apiKey    = $request->attributes->get('pilot_api_key');
        $workspace = $this->resolveTargetWorkspace($apiKey, $request->input('workspace_id'));

        if (!$workspace) {
            return back()->with('error', 'No workspace to upload into.');
        }

        $data = $request->validate([
            'workspace_id' => 'nullable|uuid',
            'data'         => 'required|string',
            'filename'     => 'required|string|max:255',
            'mime_type'    => 'nullable|string|max:120',
            'kind'         => 'nullable|in:logo,logo-mark,icon,screenshot,reference,moodboard,mockup,other',
            'description'  => 'nullable|string',
            'tags'         => 'nullable|array',
            'tags.*'       => 'string|max:50',
        ]);

        try {
            $asset = $this->assets->store($data, $workspace->id, $apiKey);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Asset “{$asset->filename}” uploaded.");
    }

    // GET /assets/{id}/raw — stream the bytes to the panel (org-scoped).
    // Served through the app rather than the `public/storage` symlink so the
    // library works regardless of whether `storage:link` ran in the image.
    public function raw(Request $request, string $id): \Symfony\Component\HttpFoundation\StreamedResponse|RedirectResponse
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $allIds = Workspace::where('org_id', $apiKey->org_id)->pluck('id')->all();

        $asset = Asset::whereIn('workspace_id', $allIds)->where('id', $id)->firstOrFail();
        $disk  = Storage::disk($asset->storage_disk);

        abort_unless($disk->exists($asset->storage_key), 404);

        return $disk->response($asset->storage_key, $asset->filename, [
            'Content-Type'  => $asset->mime_type,
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    // DELETE /assets/{id}
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $allIds = Workspace::where('org_id', $apiKey->org_id)->pluck('id')->all();

        $asset = Asset::whereIn('workspace_id', $allIds)->where('id', $id)->first();
        if (!$asset) {
            return back()->with('error', 'Asset not found.');
        }

        $name = $asset->filename;
        $this->assets->delete($asset);

        return back()->with('success', "Asset “{$name}” deleted.");
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** Panel-facing shape: public metadata + an app-served web_url (no symlink dependency). */
    private function present(Asset $a): array
    {
        return [
            ...$a->toPublicArray(),
            'web_url'  => url("/assets/{$a->id}/raw"),
            'is_image' => str_starts_with((string) $a->mime_type, 'image/'),
        ];
    }

    private function resolveTargetWorkspace($apiKey, ?string $workspaceId): ?Workspace
    {
        $query = Workspace::where('org_id', $apiKey->org_id);
        if ($workspaceId) {
            return $query->where('id', $workspaceId)->first();
        }
        return $query->orderBy('name')->first();
    }
}
