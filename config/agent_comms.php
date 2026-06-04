<?php

return [
    // Pending handshake expiry (seconds) — link_request must be answered within this window.
    'handshake_ttl'  => (int) env('AGENT_COMMS_HANDSHAKE_TTL', 300),

    // Open link auto-close after this many seconds with no message activity.
    'idle_ttl'       => (int) env('AGENT_COMMS_IDLE_TTL', 1800),

    // Presence considered stale after this many seconds without a heartbeat (online indicator only).
    'heartbeat_ttl'  => (int) env('AGENT_COMMS_HEARTBEAT_TTL', 120),

    // Long-poll cap for GET /agents/inbox?wait=N (seconds).
    'inbox_max_wait' => (int) env('AGENT_COMMS_INBOX_MAX_WAIT', 25),

    // Long-poll DB re-check interval (milliseconds).
    'inbox_poll_ms'  => (int) env('AGENT_COMMS_INBOX_POLL_MS', 1000),
];
