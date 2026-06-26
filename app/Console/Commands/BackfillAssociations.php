<?php

namespace App\Console\Commands;

use App\Models\AgentMemory;
use App\Services\MemoryService;
use Illuminate\Console\Command;

/**
 * Backfill the spreading-activation graph: every memory already carries
 * [[memory_key]] wikilinks in its prose, but those were never materialized
 * into the `associations` column, so spreadActivate() had nothing to fire.
 *
 * This parses each memory's content, resolves the wikilinks to edges within
 * the same workspace, and merges them into associations WITHOUT clobbering
 * any manually-authored edge. Runs in-process on the host (direct DB, no
 * embedding recompute — content is unchanged, so timestamps stay put).
 */
class BackfillAssociations extends Command
{
    protected $signature = 'memory:backfill-associations
        {--dry-run : Report what would change without writing}
        {--weight=0.6 : Edge weight for materialized wikilinks (>=0.5 fires)}
        {--touch : Bump updated_at (default keeps original timestamp)}';

    protected $description = 'Materialize [[wikilinks]] in memory content into association edges (spreading-activation graph)';

    public function handle(MemoryService $memory): int
    {
        $weight = (float) $this->option('weight');
        $dry    = (bool) $this->option('dry-run');

        $all = AgentMemory::orderBy('created_at')->get(['id', 'workspace_id', 'content', 'associations', 'memory_key']);
        $this->info("Scanning {$all->count()} memories for [[wikilinks]] (weight {$weight})" . ($dry ? ' — DRY RUN' : ''));

        $changed = 0;
        $edgesAdded = 0;
        $withLinks = 0;

        foreach ($all as $m) {
            $auto = $memory->wikilinkEdges($m->content ?? '', $m->workspace_id, $m->id, $weight);
            if (empty($auto)) {
                continue;
            }
            $withLinks++;

            $existing = $m->associations ?? [];
            // Existing (manual) edges win; auto wikilink edges fill the rest.
            $merged = $memory->mergeAssociations($existing, $auto) ?? [];

            $before = count($existing);
            $after  = count($merged);
            if ($after === $before) {
                continue; // every wikilink edge already present
            }

            $edgesAdded += ($after - $before);
            $changed++;

            if (! $dry) {
                $m->associations = $merged;
                if (! $this->option('touch')) {
                    $m->timestamps = false; // backfill, not a content change
                }
                $m->saveQuietly(); // no model events, no re-embed
            }

            $this->line(sprintf('  %s  %s  %d -> %d edges', $m->id, str($m->memory_key ?? '(no key)')->limit(40), $before, $after));
        }

        $this->newLine();
        $this->info("Memories with wikilinks: {$withLinks}");
        $this->info(($dry ? 'Would update' : 'Updated') . ": {$changed} memories, +{$edgesAdded} edges");

        return self::SUCCESS;
    }
}
