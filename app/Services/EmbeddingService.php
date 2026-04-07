<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    private string $host;
    private string $model;
    private int $timeout;

    public function __construct()
    {
        $this->host    = rtrim(config('services.ollama.host'), '/');
        $this->model   = config('services.ollama.embed_model');
        $this->timeout = (int) config('services.ollama.timeout', 30);
    }

    /**
     * Generate an embedding vector for the given text.
     * Returns null if Ollama is unreachable — callers must handle gracefully.
     */
    public function embed(string $text): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->host}/api/embeddings", [
                    'model'  => $this->model,
                    'prompt' => $text,
                ]);

            if ($response->failed()) {
                Log::warning('EmbeddingService: Ollama returned non-2xx', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $vector = $response->json('embedding');

            if (!is_array($vector) || empty($vector)) {
                Log::warning('EmbeddingService: Empty or invalid embedding returned', [
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $vector;

        } catch (\Exception $e) {
            Log::warning('EmbeddingService: Could not reach Ollama', [
                'error' => $e->getMessage(),
                'host'  => $this->host,
            ]);
            return null;
        }
    }

    /**
     * Compute cosine similarity between two vectors.
     * Returns a float between -1.0 and 1.0 (1.0 = identical direction).
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        $dot   = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $len = min(count($a), count($b));

        for ($i = 0; $i < $len; $i++) {
            $dot   += $a[$i] * $b[$i];
            $normA += $a[$i] ** 2;
            $normB += $b[$i] ** 2;
        }

        $denom = sqrt($normA) * sqrt($normB);

        return $denom > 0 ? round($dot / $denom, 6) : 0.0;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function host(): string
    {
        return $this->host;
    }
}
