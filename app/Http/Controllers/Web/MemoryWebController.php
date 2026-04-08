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
        $apiKey     = $request->attributes->get('pilot_api_key');
        $org        = $apiKey->organization;
        $workspaces = Workspace::where('org_id', $org->id)->orderBy('name')->get();
        $allIds     = $workspaces->pluck('id')->all();

        // Active workspace filter (null = all)
        $filterWorkspaceId = $request->input('workspace_id');
        $targetIds = $filterWorkspaceId && $workspaces->contains('id', $filterWorkspaceId)
            ? [$filterWorkspaceId]
            : $allIds;

        $q        = $request->input('q', '');
        $type     = $request->input('type', '');
        $semantic = $request->boolean('semantic');

        $memories = collect();
        $mode     = 'list';
        $embedded = false;

        if ($q && $semantic) {
            $result   = $this->memoryService->search($q, $targetIds, 30);
            $mode     = $result['embedded'] ? 'semantic' : 'keyword_fallback';
            $embedded = $result['embedded'];

            $memories = $result['results']->map(fn($r, $i) => [
                ...$r['memory']->toPublicArray(),
                '_score' => $r['score'],
                '_rank'  => $i + 1,
            ]);
        } else {
            $query = AgentMemory::whereIn('workspace_id', $targetIds)
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

        $stats = [
            'total'     => AgentMemory::whereIn('workspace_id', $allIds)->count(),
            'by_type'   => AgentMemory::whereIn('workspace_id', $allIds)
                            ->selectRaw('type, count(*) as count')
                            ->groupBy('type')
                            ->pluck('count', 'type'),
            'sensitive' => AgentMemory::whereIn('workspace_id', $allIds)->where('is_sensitive', true)->count(),
            'embedded'  => AgentMemory::whereIn('workspace_id', $allIds)->whereNotNull('embedding')->count(),
        ];

        return Inertia::render('Memory/Index', [
            'memories'            => $memories->values(),
            'stats'               => $stats,
            'filters'             => ['q' => $q, 'type' => $type, 'semantic' => $semantic, 'workspace_id' => $filterWorkspaceId],
            'search_mode'         => $mode,
            'embed_model'         => $this->embedder->model(),
            'workspaces'          => $workspaces->map(fn($w) => ['id' => $w->id, 'name' => $w->name, 'slug' => $w->slug]),
            'active_workspace_id' => $filterWorkspaceId,
        ]);
    }

    /**
     * Returns the full unmasked value of a sensitive memory.
     * Called via JS fetch from the Reveal button in the Vue page.
     */
    public function reveal(Request $request, string $id): JsonResponse
    {
        $apiKey  = $request->attributes->get('pilot_api_key');
        $org     = $apiKey->organization;
        $allIds  = Workspace::where('org_id', $org->id)->pluck('id')->all();

        $memory = AgentMemory::whereIn('workspace_id', $allIds)->findOrFail($id);

        return response()->json([
            'id'      => $memory->id,
            'label'   => $memory->label,
            'value'   => $memory->value,
            'content' => $memory->content,
            'type'    => $memory->type,
        ]);
    }
}
