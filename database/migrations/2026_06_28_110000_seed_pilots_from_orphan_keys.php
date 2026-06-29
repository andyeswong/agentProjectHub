<?php

use App\Models\ApiKey;
use App\Models\Pilot;
use Illuminate\Database\Migrations\Migration;

// Give every org a curated pilots table: for each orphan key (pilot_id null but
// a non-empty pilot string), seed one Pilot per (org, string) using the string
// as display_name, and link the keys. No identity guessing — merging variants
// stays a manual, per-name decision. Idempotent: reuses an existing same-named
// pilot in the org, and leaves already-linked keys untouched.
return new class extends Migration
{
    public function up(): void
    {
        $orphans = ApiKey::whereNull('pilot_id')
            ->whereNotNull('pilot')
            ->where('pilot', '!=', '')
            ->get(['id', 'org_id', 'pilot']);

        $orphans->groupBy(fn($k) => $k->org_id . '|' . $k->pilot)->each(function ($keys) {
            $first = $keys->first();

            $pilot = Pilot::where('org_id', $first->org_id)
                ->where('display_name', $first->pilot)
                ->first()
                ?? Pilot::create([
                    'org_id'       => $first->org_id,
                    'display_name' => $first->pilot,
                    'aliases'      => [$first->pilot],
                    'emails'       => [],
                ]);

            ApiKey::whereIn('id', $keys->pluck('id'))->update(['pilot_id' => $pilot->id]);
        });
    }

    public function down(): void
    {
        // Non-destructive seed; not reversed (would require tracking what we created).
    }
};
