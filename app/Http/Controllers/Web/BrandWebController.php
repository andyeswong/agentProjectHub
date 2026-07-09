<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Brand;
use App\Models\Workspace;
use App\Services\BrandService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Brand Board — the human-facing twin of brand_resolve. An agent gets JSON;
 * a pilot gets the identity painted: palette, type specimens, components, the
 * linked assets, and the rules. Same single source of truth.
 */
class BrandWebController extends Controller
{
    public function __construct(
        private BrandService $brands,
    ) {}

    public function index(Request $request): Response
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $wsIds  = $this->orgWorkspaceIds($apiKey);

        $brands = Brand::whereIn('workspace_id', $wsIds)
            ->withCount('assets')
            ->orderBy('slug')
            ->get()
            ->map(fn (Brand $b) => [
                'id'          => $b->id,
                'slug'        => $b->slug,
                'name'        => $b->name,
                'is_default'  => $b->is_default,
                'status'      => $b->status,
                'parent_id'   => $b->parent_id,
                'assets_count' => $b->assets_count,
                'accent'      => data_get($b->tokens, 'colors.accent'),
            ]);

        return Inertia::render('Brands/Index', [
            'brands'     => $brands->values(),
            'workspaces' => Workspace::whereIn('id', $wsIds)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** The board itself: the RESOLVED (merged) identity, so inheritance shows. */
    public function show(Request $request, string $slug): Response
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $wsIds  = $this->orgWorkspaceIds($apiKey);

        $brand = Brand::whereIn('workspace_id', $wsIds)->where('slug', $slug)->firstOrFail();
        $resolved = $this->brands->resolve($wsIds, $slug);

        // BrandService hands agents the bearer-authed API url. The panel is
        // session-authed, so point its <img> tags at the web route instead —
        // otherwise every linked asset renders as a 401.
        foreach ($resolved['assets'] ?? [] as $role => $list) {
            foreach ($list as $i => $a) {
                $resolved['assets'][$role][$i]['raw_url'] = url("/assets/{$a['id']}/raw");
            }
        }

        // Library for the attach picker — every asset in the org, images first.
        $library = Asset::whereIn('workspace_id', $wsIds)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Asset $a) => [
                'id'       => $a->id,
                'filename' => $a->filename,
                'kind'     => $a->kind,
                'is_image' => str_starts_with((string) $a->mime_type, 'image/'),
                'web_url'  => url("/assets/{$a->id}/raw"),
            ]);

        // Which assets are already linked (so the picker can mark them).
        $linked = $brand->assets()->pluck('assets.id')->all();

        return Inertia::render('Brands/Show', [
            'brand'    => [
                'id'         => $brand->id,
                'slug'       => $brand->slug,
                'parent_id'  => $brand->parent_id,
                'is_default' => $brand->is_default,
                'status'     => $brand->status,
                // This brand's OWN tokens (unmerged) — what the editor may write.
                // Editing the resolved set would bake the parent's values in here.
                'tokens'     => $brand->tokens ?? new \stdClass(),
            ],
            'resolved' => $resolved,
            'library'  => $library->values(),
            'linked'   => $linked,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $data   = $request->validate([
            'slug'        => 'required|string|max:100',
            'name'        => 'nullable|string|max:120',
            'parent_slug' => 'nullable|string|max:100',
            'is_default'  => 'nullable|boolean',
        ]);

        $workspace = Workspace::where('org_id', $apiKey->org_id)->orderBy('name')->first();
        if (! $workspace) {
            return back()->with('error', 'No workspace.');
        }

        if (! empty($data['parent_slug'])) {
            $parent = Brand::where('workspace_id', $workspace->id)->where('slug', $data['parent_slug'])->first();
            if (! $parent) {
                return back()->with('error', "No existe la marca padre “{$data['parent_slug']}”.");
            }
            $data['parent_id'] = $parent->id;
        }

        [$brand] = $this->brands->upsert($data, $workspace->id, $apiKey);

        return redirect("/brands/{$brand->slug}")->with('success', "Marca “{$brand->slug}” lista.");
    }

    /** Edit tokens / rules / voice straight from the board. */
    public function update(Request $request, string $slug): RedirectResponse
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $wsIds  = $this->orgWorkspaceIds($apiKey);

        $brand = Brand::whereIn('workspace_id', $wsIds)->where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'tokens'  => 'nullable|array',
            'rules'   => 'nullable|array',
            'rules.*' => 'string',
            'voice'   => 'nullable|array',
            'name'    => 'nullable|string|max:120',
        ]);

        $this->brands->upsert(array_merge($data, ['slug' => $slug]), $brand->workspace_id, $apiKey);

        return back()->with('success', 'Marca actualizada.');
    }

    public function attach(Request $request, string $slug): RedirectResponse
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $wsIds  = $this->orgWorkspaceIds($apiKey);

        $data = $request->validate([
            'asset_id'   => 'required|uuid',
            'role'       => 'required|in:logo,logo-mark,icon,hero-ref,moodboard,mockup,palette-ref',
            'is_primary' => 'nullable|boolean',
        ]);

        $brand = Brand::whereIn('workspace_id', $wsIds)->where('slug', $slug)->firstOrFail();
        $asset = Asset::whereIn('workspace_id', $wsIds)->findOrFail($data['asset_id']);

        $this->brands->attachAsset($brand, $asset, $data['role'], $data['is_primary'] ?? false);

        return back()->with('success', "“{$asset->filename}” enlazado como {$data['role']}.");
    }

    public function detach(Request $request, string $slug, string $assetId): RedirectResponse
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $wsIds  = $this->orgWorkspaceIds($apiKey);

        $brand = Brand::whereIn('workspace_id', $wsIds)->where('slug', $slug)->firstOrFail();
        $asset = Asset::whereIn('workspace_id', $wsIds)->findOrFail($assetId);

        $this->brands->detachAsset($brand, $asset);

        // The asset itself stays in the library — only the link is gone.
        return back()->with('success', 'Asset desenlazado (sigue en la biblioteca).');
    }

    private function orgWorkspaceIds($apiKey): array
    {
        return Workspace::where('org_id', $apiKey->org_id)->pluck('id')->all();
    }
}
