<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Exceptions\AgentCommsException;
use App\Models\ApiKey;
use Illuminate\Http\Request;

trait InteractsWithComms
{
    /**
     * Resolve the acting agent and enforce the 'comms' capability.
     */
    protected function agent(Request $request): ApiKey
    {
        /** @var ApiKey $apiKey */
        $apiKey = $request->attributes->get('api_key');

        if (! $apiKey->hasPermission('comms')) {
            throw AgentCommsException::make(
                'forbidden',
                "Missing 'comms' capability. Ask your pilot to grant it on this API key.",
                403,
            );
        }

        return $apiKey;
    }
}
