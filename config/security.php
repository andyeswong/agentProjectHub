<?php

return [
    // Per-key rate limit (req/min) for SENSITIVE operations — secret reveal
    // (GET /memory/{id}) and bulk memory search. This is a separate, tighter
    // bucket on top of the global per-key limit, to cap how fast a compromised
    // key can exfiltrate data.
    'sensitive_rate_limit' => (int) env('SENSITIVE_RATE_LIMIT', 30),
];
