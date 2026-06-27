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

    // POST /personalities — create a NEW self (its core layer).
    public function store(Request $request)
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $data = $request->validate([
            'slug'         => 'required|string|max:100|regex:/^[a-z0-9-]+$/',
            'name'         => 'required|string|max:120',
            'soul'         => 'nullable|string',
            'register'     => 'nullable|string|max:200',
            'workspace_id' => 'nullable|uuid',
        ]);

        $ws = Workspace::where('org_id', $apiKey->org_id)
            ->when($data['workspace_id'] ?? null, fn($q, $id) => $q->where('id', $id))
            ->orderBy('name')->firstOrFail();

        // Unique slug within the workspace.
        if (Personality::where('workspace_id', $ws->id)->where('slug', $data['slug'])->exists()) {
            return Redirect::back()->with('error', "A self '{$data['slug']}' already exists.");
        }

        Personality::create([
            'workspace_id' => $ws->id, 'slug' => $data['slug'], 'name' => $data['name'],
            'level' => 'core', 'soul' => $data['soul'] ?? null, 'register' => $data['register'] ?? null,
            'status' => 'active', 'created_by' => $apiKey->id, 'last_updated_by' => $apiKey->id,
        ]);

        $this->events->record('personality.created', 'personality', null, $apiKey, ['slug' => $data['slug']], $request->ip());
        return Redirect::route('personalities.index');
    }

    // POST /personalities/{slug}/layers — add a runtime or channel layer to a self.
    public function addLayer(Request $request, string $slug)
    {
        $apiKey = $request->attributes->get('pilot_api_key');
        $wsIds  = Workspace::where('org_id', $apiKey->org_id)->pluck('id');

        $core = Personality::whereIn('workspace_id', $wsIds)->where('slug', $slug)->where('level', 'core')->firstOrFail();

        $data = $request->validate([
            'level'             => 'required|in:runtime,channel',
            'match_client_type' => 'required|string|max:60',
            'match_channel'     => 'nullable|string|max:60',
            'soul'              => 'nullable|string',
            'register'          => 'nullable|string|max:200',
        ]);
        if ($data['level'] === 'channel' && empty($data['match_channel'])) {
            return Redirect::back()->with('error', 'Channel layers need a channel.');
        }

        $exists = Personality::where('workspace_id', $core->workspace_id)->where('slug', $slug)
            ->where('level', $data['level'])->where('match_client_type', $data['match_client_type'])
            ->where('match_channel', $data['match_channel'] ?? null)->exists();
        if ($exists) return Redirect::back()->with('error', 'That layer already exists.');

        Personality::create([
            'workspace_id' => $core->workspace_id, 'slug' => $slug, 'name' => $core->name,
            'parent_id' => $core->id, 'level' => $data['level'],
            'match_client_type' => $data['match_client_type'], 'match_channel' => $data['match_channel'] ?? null,
            'soul' => $data['soul'] ?? null, 'register' => $data['register'] ?? null,
            'status' => 'active', 'created_by' => $apiKey->id, 'last_updated_by' => $apiKey->id,
        ]);

        $this->events->record('personality.updated', 'personality', $core->id, $apiKey, ['slug' => $slug, 'added_layer' => $data['level']], $request->ip());
        return Redirect::route('personalities.index');
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
