<?php

namespace App\Services;

use App\Models\Personality;
use Illuminate\Support\Collection;

/**
 * Resolves a personality cascade into one effective identity a stateless body
 * can wear. The cascade is core -> runtime[client_type] -> channel[client_type
 * + channel]; deeper layers COMPLEMENT the core (scalars override, lists union,
 * souls concatenate) — never blanket replace it.
 */
class PersonalityService
{
    /**
     * Resolve the effective identity for a given body+channel.
     *
     * @return array{
     *   slug:string, name:?string, soul:string, register:?string,
     *   model_pref:?string, scopes:array, tools:array, rules:array,
     *   meta:array, layers:array, missing:array
     * }|null  null if the self has no core layer.
     */
    public function resolve(string $slug, string $workspaceId, ?string $clientType = null, ?string $channel = null): ?array
    {
        $rows = Personality::where('workspace_id', $workspaceId)
            ->where('slug', $slug)
            ->where('status', 'active')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        // Build the cascade path in merge order: core -> runtime -> channel.
        $path = [];

        $core = $rows->firstWhere('level', 'core');
        if (! $core) {
            return null; // a self without a core is not resolvable
        }
        $path[] = $core;

        if ($clientType !== null) {
            $runtime = $rows->first(fn (Personality $p) =>
                $p->level === 'runtime' && $p->match_client_type === $clientType
            );
            if ($runtime) {
                $path[] = $runtime;
            }

            if ($channel !== null) {
                $chan = $rows->first(fn (Personality $p) =>
                    $p->level === 'channel'
                    && $p->match_client_type === $clientType
                    && $p->match_channel === $channel
                );
                if ($chan) {
                    $path[] = $chan;
                }
            }
        }

        return $this->mergePath(collect($path), $slug, $clientType, $channel);
    }

    /**
     * Deep-merge an ordered cascade path (shallowest first) into one identity.
     */
    private function mergePath(Collection $path, string $slug, ?string $clientType, ?string $channel): array
    {
        $souls   = [];
        $name    = null;
        $register = null;
        $model   = null;
        $scopes  = [];
        $tools   = [];
        $rules   = [];
        $refs    = [];
        $meta    = [];
        $layers  = [];

        foreach ($path as $p) {
            // soul: concatenate core-first; deeper layers are addenda.
            if (is_string($p->soul) && trim($p->soul) !== '') {
                $souls[] = trim($p->soul);
            }
            // scalars: deepest non-null wins (last in path).
            $name     = $p->name ?: $name;
            $register = $p->register ?: $register;
            $model    = $p->model_pref ?: $model;
            // lists: union, order-preserving, deduped.
            $scopes = array_merge($scopes, $p->scopes ?? []);
            $tools  = array_merge($tools, $p->tools ?? []);
            $rules  = array_merge($rules, $p->rules ?? []);
            $refs   = array_merge($refs, $p->refs ?? []);
            // meta: shallow merge, deeper wins per key.
            $meta = array_merge($meta, $p->meta ?? []);

            $layers[] = [
                'id'                => $p->id,
                'level'             => $p->level,
                'match_client_type' => $p->match_client_type,
                'match_channel'     => $p->match_channel,
            ];
        }

        return [
            'slug'        => $slug,
            'name'        => $name,
            'soul'        => implode("\n\n", $souls),
            'register'    => $register,
            'model_pref'  => $model,
            'scopes'      => array_values(array_unique($scopes)),
            'tools'       => array_values(array_unique($tools)),
            'rules'       => $this->dedupePreserveOrder($rules),
            'refs'        => $this->dedupeRefs($refs),
            'meta'        => $meta,
            'resolved_for'=> ['client_type' => $clientType, 'channel' => $channel],
            'layers'      => $layers,                       // provenance: what merged
            'missing'     => $this->missingLayers($clientType, $channel, $layers),
        ];
    }

    /**
     * Note which requested layers had no matching node — so the caller knows
     * it fell back to a shallower layer (core-only, or runtime without channel).
     */
    private function missingLayers(?string $clientType, ?string $channel, array $layers): array
    {
        $have = array_column($layers, 'level');
        $missing = [];
        if ($clientType !== null && ! in_array('runtime', $have, true)) {
            $missing[] = "runtime:{$clientType}";
        }
        if ($channel !== null && ! in_array('channel', $have, true)) {
            $missing[] = "channel:{$clientType}/{$channel}";
        }
        return $missing;
    }

    /**
     * Dedupe typed reference pointers by (kind + ref). Deeper layers override
     * a shallower pointer to the same artifact (refine its `when`/`load`), but
     * the artifact appears once. Malformed entries (no ref) are dropped.
     */
    private function dedupeRefs(array $refs): array
    {
        $byKey = [];
        foreach ($refs as $r) {
            if (! is_array($r) || empty($r['ref'])) {
                continue;
            }
            $key = ($r['kind'] ?? 'memory') . ':' . $r['ref'];
            $byKey[$key] = $r; // last (deepest) wins
        }
        return array_values($byKey);
    }

    /** Dedupe a list of strings keeping first occurrence order. */
    private function dedupePreserveOrder(array $list): array
    {
        $seen = [];
        $out  = [];
        foreach ($list as $item) {
            $k = is_string($item) ? $item : json_encode($item);
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $out[] = $item;
        }
        return $out;
    }
}
