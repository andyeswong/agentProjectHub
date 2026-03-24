<?php

namespace App\Services;

use App\Models\ActivityEvent;
use App\Models\ApiKey;

class ActivityEventService
{
    public function record(
        string $eventType,
        string $entityType,
        string $entityId,
        ApiKey $actor,
        array $payload = [],
        ?string $ipAddress = null
    ): ActivityEvent {
        return ActivityEvent::create([
            'event_type'       => $eventType,
            'entity_type'      => $entityType,
            'entity_id'        => $entityId,
            'actor_api_key_id' => $actor->id,
            'actor_model'      => $actor->model,
            'payload'          => $payload,
            'ip_address'       => $ipAddress,
        ]);
    }
}
