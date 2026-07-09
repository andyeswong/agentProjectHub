<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\Asset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Assets (F1). Stores binary files on a Storage disk + metadata row, embeds the
 * description for semantic search. This is the first file-storage code in the
 * app; everything else follows the memory conventions (embed on write, ANN on
 * Postgres, cosine-in-PHP fallback elsewhere).
 */
class AssetService
{
    /** Default disk for asset bytes (MVP: local 'public' PVC). */
    private const DEFAULT_DISK = 'public';

    public function __construct(
        private EmbeddingService $embedder,
        private ActivityEventService $events,
    ) {}

    /**
     * Decode a base64 upload, persist the bytes to a disk, and record the asset
     * with an embedded description.
     */
    public function store(array $data, string $workspaceId, ApiKey $actor): Asset
    {
        $bytes = base64_decode($data['data'] ?? '', true);
        if ($bytes === false || $bytes === '') {
            throw new \InvalidArgumentException('`data` must be non-empty base64.');
        }

        [$width, $height, $sniffedMime] = $this->probe($bytes);
        $mime     = $data['mime_type'] ?? $sniffedMime ?? 'application/octet-stream';
        $size     = strlen($bytes);
        $checksum = hash('sha256', $bytes);

        $disk = $data['storage_disk'] ?? self::DEFAULT_DISK;
        $ext  = $this->extensionFor($mime, $data['filename'] ?? null);
        $key  = "assets/{$workspaceId}/" . Str::uuid()->toString() . ($ext ? ".{$ext}" : '');

        Storage::disk($disk)->put($key, $bytes);

        $description = $data['description'] ?? null;
        $embedding   = null;
        if ($description) {
            $embedding = $this->embedder->embed($this->buildEmbedText($data + ['mime' => $mime]));
        }

        $asset = Asset::create([
            'workspace_id'    => $workspaceId,
            'kind'            => $data['kind'] ?? 'other',
            'filename'        => $data['filename'] ?? "asset{$ext}",
            'mime_type'       => $mime,
            'size'            => $size,
            'width'           => $width,
            'height'          => $height,
            'storage_disk'    => $disk,
            'storage_key'     => $key,
            'checksum'        => $checksum,
            'description'     => $description,
            'embedding'       => $embedding,
            'embedding_model' => $embedding ? $this->embedder->model() : null,
            'tags'            => $data['tags'] ?? null,
            'brand_hint'      => $data['brand_hint'] ?? null,
            'created_by'      => $actor->id,
        ]);

        $this->events->record('asset.created', 'asset', $asset->id, $actor, [
            'filename' => $asset->filename,
            'kind'     => $asset->kind,
            'size'     => $asset->size,
        ]);

        return $asset;
    }

    /**
     * Semantic search over asset descriptions. Mirrors MemoryService::search:
     * indexed ANN on Postgres (embedding_vec <=> query), cosine-in-PHP fallback
     * on sqlite/non-pgvector.
     *
     * @return array{results: \Illuminate\Support\Collection, embedded: bool, fallback: ?string}
     */
    public function search(string $query, array|string $workspaceIds, int $limit = 20): array
    {
        $queryVector = $this->embedder->embed($query);
        $ids         = (array) $workspaceIds;

        if (!$queryVector) {
            return ['results' => collect(), 'embedded' => false, 'fallback' => 'keyword'];
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            $literal = "'[" . implode(',', array_map(fn ($f) => (float) $f, $queryVector)) . "]'";

            $rows = Asset::query()
                ->whereIn('workspace_id', $ids)
                ->whereNotNull('embedding_vec')
                ->select([
                    'id', 'workspace_id', 'kind', 'filename', 'mime_type', 'size',
                    'width', 'height', 'storage_disk', 'storage_key', 'checksum',
                    'description', 'embedding', 'embedding_model', 'tags', 'brand_hint',
                    'created_by', 'created_at', 'updated_at',
                ])
                ->selectRaw("(embedding_vec <=> {$literal}::vector) AS distance")
                ->orderByRaw("embedding_vec <=> {$literal}::vector")
                ->limit($limit)
                ->get();

            $scored = $rows->map(fn (Asset $a) => [
                'asset' => $a,
                'score' => round(1 - (float) $a->distance, 6),
            ])->values();

            return ['results' => $scored, 'embedded' => true, 'fallback' => null];
        }

        // Fallback (sqlite/non-pgvector): load embedded rows and cosine in PHP.
        $assets = Asset::whereIn('workspace_id', $ids)
            ->whereNotNull('embedding')
            ->get();

        $scored = $assets
            ->map(fn (Asset $a) => [
                'asset' => $a,
                'score' => $this->embedder->cosineSimilarity($queryVector, $a->embedding),
            ])
            ->sortByDesc('score')
            ->take($limit)
            ->values();

        return ['results' => $scored, 'embedded' => true, 'fallback' => null];
    }

    /** Delete the row and its bytes from disk. */
    public function delete(Asset $asset): void
    {
        Storage::disk($asset->storage_disk)->delete($asset->storage_key);
        $asset->delete();
    }

    /** Build the text that gets embedded — kind + filename + description + tags. */
    private function buildEmbedText(array $data): string
    {
        $parts = [];
        if (!empty($data['kind']))        { $parts[] = "Kind: {$data['kind']}"; }
        if (!empty($data['filename']))    { $parts[] = "File: {$data['filename']}"; }
        if (!empty($data['description'])) { $parts[] = $data['description']; }
        if (!empty($data['tags']))        { $parts[] = 'Tags: ' . implode(', ', (array) $data['tags']); }

        return implode('. ', $parts);
    }

    /** Detect image dimensions + mime from raw bytes (no external deps). */
    private function probe(string $bytes): array
    {
        $info = @getimagesizefromstring($bytes);
        if ($info !== false) {
            return [$info[0] ?? null, $info[1] ?? null, $info['mime'] ?? null];
        }
        return [null, null, null];
    }

    /** Pick a file extension from mime, falling back to the original filename. */
    private function extensionFor(string $mime, ?string $filename): string
    {
        $map = [
            'image/png'     => 'png',
            'image/jpeg'    => 'jpg',
            'image/webp'    => 'webp',
            'image/gif'     => 'gif',
            'image/svg+xml' => 'svg',
            'image/avif'    => 'avif',
            'application/pdf' => 'pdf',
        ];
        if (isset($map[$mime])) {
            return $map[$mime];
        }
        $ext = $filename ? pathinfo($filename, PATHINFO_EXTENSION) : '';
        return $ext ? strtolower($ext) : '';
    }
}
