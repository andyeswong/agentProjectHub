<?php

use App\Models\ApiKey;
use App\Models\Pilot;
use Illuminate\Database\Migrations\Migration;

// One-shot data fix: the pilots table already holds canonical display_name +
// aliases, but api_keys.pilot (denormalized free-text) still carries the old
// variants, and a few keys have no pilot_id. Sync the string from the canonical
// source and attach the orphan keys to their human.
return new class extends Migration
{
    public function up(): void
    {
        // 1) Canonicalize denormalized api_keys.pilot from pilots.display_name.
        Pilot::all()->each(function (Pilot $p) {
            ApiKey::where('pilot_id', $p->id)->update(['pilot' => $p->display_name]);
        });

        // 2) Attach orphan keys (no pilot_id) to the right human, per org.
        $assign = [
            'Cemdi app'          => 'Fernando Medina',
            'claudecode desktop' => 'Andres Wong',
            'whatsapp orch'      => 'Andres Wong',
        ];

        foreach ($assign as $oldLabel => $target) {
            ApiKey::whereNull('pilot_id')->where('pilot', $oldLabel)->get()->each(function (ApiKey $k) use ($target) {
                $pilot = Pilot::where('org_id', $k->org_id)->where('display_name', $target)->first();
                if (!$pilot) {
                    return;
                }
                // Preserve the old free-text label as an alias of the canonical pilot.
                $aliases = $pilot->aliases ?? [];
                if (!in_array($k->pilot, $aliases, true)) {
                    $aliases[] = $k->pilot;
                    $pilot->update(['aliases' => $aliases]);
                }
                $k->update(['pilot_id' => $pilot->id, 'pilot' => $target]);
            });
        }
    }

    public function down(): void
    {
        // Irreversible data normalization — original free-text variants are not restored.
    }
};
