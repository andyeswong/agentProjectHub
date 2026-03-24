<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'pilot' => session('pilot_name'),
                'agent' => session('agent_id') ? [
                    'id'          => session('agent_id'),
                    'model'       => session('agent_model'),
                    'client_type' => session('agent_client_type'),
                    'permissions' => session('agent_permissions'),
                ] : null,
                'org' => session('org_id') ? [
                    'id'   => session('org_id'),
                    'name' => session('org_name'),
                    'slug' => session('org_slug'),
                ] : null,
            ],
            'flash' => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ];
    }
}
