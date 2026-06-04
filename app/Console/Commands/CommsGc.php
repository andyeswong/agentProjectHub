<?php

namespace App\Console\Commands;

use App\Services\AgentLinkService;
use Illuminate\Console\Command;

/**
 * Expire stale handshakes (pending past TTL) and idle-close open links.
 * Schedule it every minute in production.
 */
class CommsGc extends Command
{
    protected $signature = 'comms:gc';
    protected $description = 'Expire stale agent handshakes and idle-close open links';

    public function handle(AgentLinkService $links): int
    {
        $links->expireStale();
        $this->info('Agent Channels GC done.');
        return self::SUCCESS;
    }
}
