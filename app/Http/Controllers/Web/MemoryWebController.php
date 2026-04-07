<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AgentMemory;
use App\Models\Workspace;
use App\Services\EmbeddingService;
use App\Services\MemoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemoryWebController extends Controller
{
    public function __construct(
        private MemoryService $memoryService,
        private EmbeddingService $embedder,
    ) {}

    public function index(Request $request): Response
    {
        $apiKey    = $request->attributes->get('pilot_api_key');
        $workspace = $this->resolveWorkspace($apiKey);

        $q        = $request->input('q', '');
        $type     = $request->input('type', '');
        $semantic = $request->boolean('semantic');

        $memories  = collect();
        $scores    = [];
        $mode      = 'list';
        $embedded  = false;

        if ($q && $semantic) {
            // Vector search
            $result = $this->memoryService->search($q, $workspace?->id ?? '', 30);
            $mode   = $result['embedded'] ? 'semantic' : 'keyword_fallback';
            $embedded = $result['embedded'];

            $memories = $result['results']->map(fn($r, $i) => [
                ...$r['memory']->toPublicArray(),
                '_score' => $r['score'],
                '_rank'  => $i + 1,
            ]);
        } elseif ($workspace) {
            $query = AgentMemory::where('workspace_id', $workspace->id)
                ->where(fn($q2) => $q2->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->with(['creator', 'lastEditor']);

            if ($q) {
                $query->where(fn($q2) =>
                    $q2->where('label', 'like', "%{$q}%")
                       ->orWhere('content', 'like', "%{$q}%")
                       ->orWhere('memory_key', 'like', "%{$q}%")
                );
                $mode = 'keyword';
            }

            if ($type) {
                $query->where('type', $type);
            }

            $memories = $query->orderByDesc('created_at')
                ->get()
                ->map(fn($m) => $m->toPublicArray());
        }

        $stats = $workspace ? [
            'total'       => AgentMemory::where('workspace_id', $workspace->id)->count(),
            'by_type'     => AgentMemory::where('workspace_id', $workspace->id)
                                ->selectRaw('type, count(*) as count')
                                ->groupBy('type')
                                ->pluck('count', 'type'),
            'sensitive'   => AgentMemory::where('workspace_id', $workspace->id)->where('is_sensitive', true)->count(),
            'embedded'    => AgentMemory::where('workspace_id', $workspace->id)->whereNotNull('embedding')->count(),
        ] : null;

        return Inertia::render('Memory/Index', [
            'memories'       => $memories->values(),
            'stats'          => $stats,
            'filters'        => ['q' => $q, 'type' => $type, 'semantic' => $semantic],
            'search_mode'    => $mode,
            'embed_model'    => $this->embedder->model(),
            'workspace_name' => $workspace?->name,
        ]);
    }

    /**
     * Returns the full unmasked value of a sensitive memory.
     * Called via JS fetch from the Reveal button in the Vue page.
     */
    public function reveal(Request $request, string $id): JsonResponse
    {
        $apiKey    = $request->attributes->get('pilot_api_key');
        $workspace = $this->resolveWorkspace($apiKey);

        $memory = AgentMemory::where('workspace_id', $workspace?->id ?? '')
            ->findOrFail($id);

        return response()->json([
            'id'    => $memory->id,
            'label' => $memory->label,
            'value' => $memory->value,
            'content' => $memory->content,
            'type'  => $memory->type,
        ]);
    }

    private function resolveWorkspace($apiKey): ?Workspace
    {
        if ($apiKey->workspace_id) {
            return Workspace::find($apiKey->workspace_id);
        }
        return Workspace::where('org_id', $apiKey->org_id)->first();
    }
}
