<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off data migration: copy all domain data from the legacy MySQL DB into
 * the new Postgres DB. Connections are built explicitly from options/env so it
 * can run from anywhere regardless of the app's configured DB_CONNECTION.
 *
 * Booleans are cast (MySQL tinyint 0/1 -> PG boolean). Tables are truncated
 * child-first then inserted parent-first to respect foreign keys without
 * needing superuser to disable triggers.
 */
class CopyMysqlToPgsql extends Command
{
    protected $signature = 'db:copy-mysql-to-pgsql
        {--mysql-host=192.168.35.125}
        {--mysql-db=project_hub}
        {--mysql-user=entdev}
        {--pg-host=127.0.0.1}
        {--pg-db=project_hub}
        {--pg-user=projecthub}';

    protected $description = 'Copy all data from the legacy MySQL DB to Postgres (mysql pass via SRC_MYSQL_PASS env, pg pass via DST_PG_PASS env)';

    /** Parent-first order (FK-safe for inserts). Truncate runs in reverse. */
    private array $tables = [
        'organizations', 'workspaces', 'api_keys', 'pilot_tokens',
        'projects', 'tasks', 'comments', 'activity_events',
        'agent_memories', 'agent_presence', 'agent_links', 'agent_messages',
    ];

    public function handle(): int
    {
        $srcPass = getenv('SRC_MYSQL_PASS');
        $dstPass = getenv('DST_PG_PASS');
        if ($srcPass === false || $dstPass === false) {
            $this->error('Set SRC_MYSQL_PASS and DST_PG_PASS env vars.');
            return self::FAILURE;
        }

        config(['database.connections._src' => [
            'driver' => 'mysql', 'host' => $this->option('mysql-host'), 'port' => 3306,
            'database' => $this->option('mysql-db'), 'username' => $this->option('mysql-user'),
            'password' => $srcPass, 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
        ]]);
        config(['database.connections._dst' => [
            'driver' => 'pgsql', 'host' => $this->option('pg-host'), 'port' => 5432,
            'database' => $this->option('pg-db'), 'username' => $this->option('pg-user'),
            'password' => $dstPass, 'charset' => 'utf8', 'search_path' => 'public', 'sslmode' => 'prefer',
        ]]);

        $src = DB::connection('_src');
        $dst = DB::connection('_dst');

        // Truncate target child-first.
        foreach (array_reverse($this->tables) as $t) {
            $dst->table($t)->delete();
        }

        $grand = 0;
        foreach ($this->tables as $t) {
            $boolCols = $dst->table('information_schema.columns')
                ->where('table_schema', 'public')->where('table_name', $t)
                ->where('data_type', 'boolean')->pluck('column_name')->all();

            $rows = $src->table($t)->get();
            $batch = [];
            $n = 0;
            foreach ($rows as $r) {
                $a = (array) $r;
                foreach ($boolCols as $b) {
                    if (array_key_exists($b, $a) && $a[$b] !== null) {
                        $a[$b] = (bool) $a[$b];
                    }
                }
                $batch[] = $a;
                if (count($batch) >= 500) {
                    $dst->table($t)->insert($batch);
                    $n += count($batch);
                    $batch = [];
                }
            }
            if ($batch) {
                $dst->table($t)->insert($batch);
                $n += count($batch);
            }
            $this->line(sprintf('%-18s %6d', $t, $n));
            $grand += $n;
        }

        $this->info("Copied {$grand} rows across " . count($this->tables) . ' tables.');
        return self::SUCCESS;
    }
}
