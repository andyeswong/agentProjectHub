<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Brand;
use App\Models\Project;

/**
 * Brands (F2). Two jobs:
 *
 *  1. WHICH brand applies — project.brand_id, else the workspace default.
 *  2. WHAT that brand resolves to — deep-merge of its parent chain, root first,
 *     mirroring PersonalityService::mergePath(). Deeper layers COMPLEMENT the
 *     root; they never blanket-replace it.
 *
 * `tokens` and `voice` merge recursively (deepest key wins). `rules` union and
 * dedupe. Linked assets union across the chain, keyed by role (a child's asset
 * for a role shadows the parent's).
 */
class BrandService
{
    public function __construct(
        private ActivityEventService $events,
    ) {}

    /**
     * Resolve the effective brand for a project / workspace / explicit slug.
     * Returns null when nothing applies.
     *
     * @param  array<string>  $workspaceIds  org-scoped candidate workspaces
     */
    public function resolve(array $workspaceIds, ?string $slug = null, ?string $projectId = null, ?string $workspaceId = null): ?array
    {
        $brand = $this->pick($workspaceIds, $slug, $projectId, $workspaceId);
        if (! $brand) {
            return null;
        }

        $chain = $this->chain($brand);       // root .. self
        return $this->mergeChain($chain);
    }

    /** Decide WHICH brand applies. Explicit slug > project pointer > workspace default. */
    private function pick(array $workspaceIds, ?string $slug, ?string $projectId, ?string $workspaceId): ?Brand
    {
        $base = Brand::whereIn('workspace_id', $workspaceIds)->where('status', 'active');

        if ($slug) {
            return (clone $base)->where('slug', $slug)->first();
        }

        if ($projectId) {
            $project = Project::whereIn('workspace_id', $workspaceIds)->find($projectId);
            if ($project?->brand_id) {
                return (clone $base)->where('id', $project->brand_id)->first();
            }
            // Project with no brand -> fall back to its own workspace's default.
            if ($project) {
                return (clone $base)->where('workspace_id', $project->workspace_id)->where('is_default', true)->first();
            }
            return null;
        }

        $scope = $workspaceId ? [$workspaceId] : $workspaceIds;
        return Brand::whereIn('workspace_id', $scope)->where('status', 'active')
            ->where('is_default', true)->first();
    }

    /**
     * Walk parent_id up to the root, return the chain root-first.
     * Cycle-guarded: a malformed parent loop stops instead of hanging.
     *
     * @return array<int,Brand>
     */
    private function chain(Brand $brand): array
    {
        $chain = [];
        $seen  = [];
        $node  = $brand;

        while ($node && ! isset($seen[$node->id])) {
            $seen[$node->id] = true;
            array_unshift($chain, $node);          // root ends up first
            $node = $node->parent_id ? Brand::find($node->parent_id) : null;
        }

        return $chain;
    }

    /** @param array<int,Brand> $chain root-first */
    private function mergeChain(array $chain): array
    {
        $self   = end($chain);
        $tokens = [];
        $voice  = [];
        $rules  = [];
        $meta   = [];
        $name   = null;
        $layers = [];

        foreach ($chain as $b) {
            $tokens = $this->deepMerge($tokens, $b->tokens ?? []);
            $voice  = $this->deepMerge($voice,  $b->voice  ?? []);
            $meta   = array_merge($meta, $b->meta ?? []);          // shallow: deepest key wins
            $rules  = array_merge($rules, $b->rules ?? []);        // union
            $name   = $b->name ?: $name;                            // deepest non-null wins

            $layers[] = ['slug' => $b->slug, 'id' => $b->id];
        }

        return [
            'slug'         => $self->slug,
            'name'         => $name,
            'tokens'       => $tokens,
            'voice'        => $voice ?: null,
            'rules'        => array_values(array_unique($rules)),
            'meta'         => $meta ?: null,
            'assets'       => $this->assetsByRole($chain),
            'layers'       => $layers,     // provenance: what merged, root first
            'inherits'     => count($chain) > 1,
        ];
    }

    /**
     * Union linked assets across the chain, grouped by role. A deeper brand's
     * asset for a role shadows the ancestor's — that's how a variant swaps its
     * logo while keeping the parent's moodboard.
     *
     * @param array<int,Brand> $chain root-first
     */
    private function assetsByRole(array $chain): array
    {
        $byRole = [];

        foreach ($chain as $b) {                    // root first, so children overwrite
            foreach ($b->assets as $asset) {
                $role = $asset->pivot->role;
                $byRole[$role][$asset->id] = [
                    'id'         => $asset->id,
                    'filename'   => $asset->filename,
                    'mime_type'  => $asset->mime_type,
                    'width'      => $asset->width,
                    'height'     => $asset->height,
                    'raw_url'    => $asset->rawUrl(),
                    'is_primary' => (bool) $asset->pivot->is_primary,
                    'sort_order' => (int) $asset->pivot->sort_order,
                    'from_brand' => $b->slug,
                ];
            }
        }

        // Flatten, ordered by sort_order within each role.
        foreach ($byRole as $role => $assets) {
            $list = array_values($assets);
            usort($list, fn ($a, $c) => $a['sort_order'] <=> $c['sort_order']);
            $byRole[$role] = $list;
        }

        return $byRole;
    }

    /** Recursive array merge where the later (deeper) value wins per key. */
    private function deepMerge(array $base, array $over): array
    {
        foreach ($over as $k => $v) {
            $base[$k] = (is_array($v) && isset($base[$k]) && is_array($base[$k]))
                ? $this->deepMerge($base[$k], $v)
                : $v;
        }
        return $base;
    }

    // ── Mutations ───────────────────────────────────────────────────────────

    /**
     * Would pointing $brand at $newParentId close a loop? A brand cannot
     * inherit from itself, nor from any of its own descendants.
     */
    public function wouldCycle(?Brand $brand, ?string $newParentId): bool
    {
        if (! $brand || ! $newParentId) {
            return false;
        }
        if ($newParentId === $brand->id) {
            return true;
        }

        $seen = [];
        $node = Brand::find($newParentId);
        while ($node && ! isset($seen[$node->id])) {
            if ($node->id === $brand->id) {
                return true;   // the candidate parent descends from us
            }
            $seen[$node->id] = true;
            $node = $node->parent_id ? Brand::find($node->parent_id) : null;
        }

        return false;
    }

    /** Idempotent upsert on (workspace_id, slug). Bumps version on update. */
    public function upsert(array $data, string $workspaceId, $actor): array
    {
        $existing = Brand::where('workspace_id', $workspaceId)->where('slug', $data['slug'])->first();

        // Distinguish "parent not mentioned" (keep it) from "parent = null"
        // (detach). A plain ?? would make disinheriting impossible.
        $parentId = array_key_exists('parent_id', $data)
            ? $data['parent_id']
            : ($existing->parent_id ?? null);

        $attrs = [
            'name'            => $data['name']       ?? ($existing->name ?? null),
            'parent_id'       => $parentId,
            'tokens'          => $data['tokens']     ?? ($existing->tokens ?? null),
            'voice'           => $data['voice']      ?? ($existing->voice ?? null),
            'rules'           => $data['rules']      ?? ($existing->rules ?? null),
            'meta'            => $data['meta']       ?? ($existing->meta ?? null),
            'is_default'      => $data['is_default'] ?? ($existing->is_default ?? false),
            'status'          => $data['status']     ?? ($existing->status ?? 'active'),
            'last_updated_by' => $actor->id,
            'version'         => $existing ? $existing->version + 1 : 1,
        ];

        if ($existing) {
            $existing->update($attrs);
            $brand = $existing->fresh();
            $event = 'brand.updated';
        } else {
            $brand = Brand::create(array_merge($attrs, [
                'workspace_id' => $workspaceId,
                'slug'         => $data['slug'],
                'created_by'   => $actor->id,
            ]));
            $event = 'brand.created';
        }

        // Only one default per workspace.
        if ($brand->is_default) {
            Brand::where('workspace_id', $workspaceId)->where('id', '!=', $brand->id)
                ->update(['is_default' => false]);
        }

        $this->events->record($event, 'brand', $brand->id, $actor, ['slug' => $brand->slug]);

        return [$brand, $event === 'brand.created'];
    }

    public function attachAsset(Brand $brand, Asset $asset, string $role, bool $isPrimary = false, int $sort = 0): void
    {
        $brand->assets()->syncWithoutDetaching([
            $asset->id => ['role' => $role, 'is_primary' => $isPrimary, 'sort_order' => $sort],
        ]);
    }

    public function detachAsset(Brand $brand, Asset $asset): void
    {
        $brand->assets()->detach($asset->id);
    }
}
