<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AgentMemory;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;

class WorkspaceWebController extends Controller
{
    // POST /workspaces — create a workspace in the pilot's org.
    public function store(Request $request)
    {
        $orgId = $request->attributes->get('pilot_api_key')->org_id;
        $data  = $request->validate(['name' => 'required|string|max:120']);

        Workspace::create([
            'org_id' => $orgId,
            'name'   => $data['name'],
            'slug'   => $this->uniqueSlug($orgId, $data['name']),
        ]);

        return Redirect::back();
    }

    // PATCH /workspaces/{id} — rename.
    public function update(Request $request, string $id)
    {
        $ws   = $this->find($request, $id);
        $data = $request->validate(['name' => 'required|string|max:120']);
        $ws->update(['name' => $data['name']]);

        return Redirect::back();
    }

    // DELETE /workspaces/{id} — policy: delete only if empty; otherwise the
    // caller must pass move_to to relocate ALL memories + projects first.
    // No path destroys memories (workspace_id cascades, so we never delete a
    // non-empty workspace without moving its content out).
    public function destroy(Request $request, string $id)
    {
        $ws        = $this->find($request, $id);
        $orgId     = $ws->org_id;
        $memCount  = AgentMemory::where('workspace_id', $ws->id)->count();
        $projCount = Project::where('workspace_id', $ws->id)->count();

        if ($memCount === 0 && $projCount === 0) {
            $ws->delete();
            return Redirect::route('memory.index');
        }

        $data = $request->validate(['move_to' => 'required|uuid']);
        $target = Workspace::where('org_id', $orgId)->where('id', $data['move_to'])
            ->where('id', '!=', $ws->id)->first();
        if (!$target) {
            return Redirect::back()->withErrors(['move_to' => 'Pick a valid destination workspace (not this one).']);
        }

        DB::transaction(function () use ($ws, $target) {
            // memory_key is unique per workspace — suffix any key the target owns.
            $targetKeys = AgentMemory::where('workspace_id', $target->id)
                ->whereNotNull('memory_key')->pluck('memory_key')->flip();

            AgentMemory::where('workspace_id', $ws->id)->whereNotNull('memory_key')
                ->get(['id', 'memory_key'])
                ->each(function ($m) use ($targetKeys) {
                    if ($targetKeys->has($m->memory_key)) {
                        $m->update(['memory_key' => $m->memory_key . '-' . Str::lower(Str::random(4))]);
                    }
                });

            AgentMemory::where('workspace_id', $ws->id)->update(['workspace_id' => $target->id]);
            Project::where('workspace_id', $ws->id)->update(['workspace_id' => $target->id]);

            $ws->delete();
        });

        return Redirect::route('memory.index', ['workspace_id' => $target->id]);
    }

    private function find(Request $request, string $id): Workspace
    {
        $orgId = $request->attributes->get('pilot_api_key')->org_id;
        return Workspace::where('org_id', $orgId)->findOrFail($id);
    }

    private function uniqueSlug(string $orgId, string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $i = 2;
        while (Workspace::where('org_id', $orgId)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
