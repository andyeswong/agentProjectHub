<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $dbStatus = 'ok';
        try {
            DB::connection()->getPdo();
        } catch (\Exception) {
            $dbStatus = 'error';
        }

        return response()->json([
            'status'  => $dbStatus === 'ok' ? 'ok' : 'degraded',
            'version' => 'v1',
            'services' => [
                'database' => $dbStatus,
            ],
            'timestamp' => now()->toISOString(),
        ], $dbStatus === 'ok' ? 200 : 503);
    }
}
