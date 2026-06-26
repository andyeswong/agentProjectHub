<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Personality;
use App\Models\Workspace;
use App\Services\ActivityEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class PersonalityWebController extends Controller
{
    public function __construct(private ActivityEventService $events) {}

    // GET /personalities — every self as a cascade tree (core -> runtime -> channel).
    public function index(Request $request): Response
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $wsIds  = Workspace::where('org_id', $apiKey->org_id)->pluck('id');

        $rows = Personality::whereIn('workspace_id', $wsIds)
            ->orderBy('slug')
            ->orderByRaw("CASE level WHEN 'core' THEN 0 WHEN 'runtime' THEN 1 ELSE 2 END")
            ->orderBy('match_client_type')->orderBy('match_channel')
            ->get();

        $selves = $rows->groupBy('slug')->map(function ($layers, $slug) {
            $core = $layers->firstWhere('level', 'core');
            return [
                'slug'   => $slug,
                'name'   => $core?->name ?? $slug,
                'layers' => $layers->map(fn($l) => [
                    'id'                => $l->id,
                    'level'             => $l->level,
                    'match_client_type' => $l->match_client_type,
                    'match_channel'     => $l->match_channel,
                    'soul'              => $l->soul,
                    'register'          => $l->register,
                    'model_pref'        => $l->model_pref,
                    'scopes'            => $l->scopes ?? [],
                    'tools'             => $l->tools ?? [],
                    'rules'             => $l->rules ?? [],
                    'refs'              => $l->refs ?? [],
                    'status'            => $l->status,
                    'version'           => $l->version,
                ])->values(),
            ];
        })->values();

        return Inertia::render('Personalities/Index', ['selves' => $selves]);
    }

    // PATCH /personality-layer/{id} — edit a layer's text fields + status (the gate).
    public function updateLayer(Request $request, string $id)
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $wsIds  = Workspace::where('org_id', $apiKey->org_id)->pluck('id');
        $layer  = Personality::whereIn('workspace_id', $wsIds)->findOrFail($id);

        $data = $request->validate([
            'soul'       => 'sometimes|nullable|string',
            'register'   => 'sometimes|nullable|string|max:200',
            'model_pref' => 'sometimes|nullable|string|max:120',
            'rules'      => 'sometimes|array',
            'rules.*'    => 'string',
            'scopes'     => 'sometimes|array',
            'scopes.*'   => 'string',
            'status'     => 'sometimes|in:draft,active',
        ]);

        $layer->fill($data);
        $layer->version = ($layer->version ?? 1) + 1;
        $layer->last_updated_by = $apiKey->id;
        $layer->save();

        $this->events->record('personality.updated', 'personality', $layer->id, $apiKey, [
            'slug' => $layer->slug, 'level' => $layer->level, 'fields' => array_keys($data),
        ], $request->ip());

        return Redirect::back();
    }
}
