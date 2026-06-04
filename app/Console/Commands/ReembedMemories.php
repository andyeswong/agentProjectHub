<?php

namespace App\Console\Commands;

use App\Models\AgentMemory;
use App\Services\EmbeddingService;
use App\Services\MemoryService;
use Illuminate\Console\Command;

/**
 * Backfill embeddings for memories that were stored while the embedder was
 * unreachable or rejecting long input. Runs in-process on the host so it talks
 * to the DB directly — no HTTP/API round-trip, no read-replica races.
 */
class ReembedMemories extends Command
{
    protected $signature = 'memory:reembed
        {--dry-run : Only report how many memories are missing an embedding}
        {--touch : Bump updated_at on re-embed (default keeps the original timestamp)}';

    protected $description = 'Re-embed every AgentMemory that has no embedding vector';

    public function handle(EmbeddingService $embedder, MemoryService $memory): int
    {
        $pending = AgentMemory::whereNull('embedding')
            ->orderBy('created_at')
            ->get();

        $total = $pending->count();
        $this->info("Memories without embedding: {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }
        if ($this->option('dry-run')) {
            $this->line('Dry run — nothing changed.');
            return self::SUCCESS;
        }

        $ok = 0;
        $failed = [];

        foreach ($pending as $m) {
            $text   = $memory->buildEmbedTextPublic($m->toArray());
            $vector = $embedder->embed($text);

            if (is_array($vector) && count($vector) > 0) {
                $m->embedding       = $vector;
                $m->embedding_model = $embedder->model();
                if (! $this->option('touch')) {
                    $m->timestamps = false; // backfill, not a content change
                }
                $m->saveQuietly();          // skip model events
                $ok++;
                $this->output->write('.');
            } else {
                $failed[] = $m->id;
                $this->output->write('F');
            }
        }

        $this->newLine();
        $this->info("Re-embedded: {$ok}  Failed: " . count($failed));

        if ($failed) {
            $this->warn('Failed IDs (Ollama unreachable/empty): ' . implode(', ', $failed));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
