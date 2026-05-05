<?php

namespace Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Integrations\Exceptions\IntegrationException;
use Modules\Integrations\Models\IntegrationProvider;
use Modules\Integrations\Services\IntegrationManager;
use Modules\Integrations\Transformers\IntegrationProviderResource;

class IntegrationsController extends Controller
{
    public function connect(IntegrationProvider $provider, Request $request, IntegrationManager $manager): RedirectResponse|JsonResponse
    {
        try {
            $tenant = auth('api')->user()?->tenant;

            if ($tenant === null) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            $authUrl = $manager->connect($provider, $tenant);

            return redirect()->away($authUrl);
        } catch (IntegrationException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status());
        }
    }

    public function callback(Request $request, IntegrationManager $manager): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        try {
            $connection = $manager->callback($validated['state'], $validated['code']);

            return redirect()->away(route('api.integrations.index'));
        } catch (IntegrationException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status());
        }
    }

    public function disconnect(IntegrationProvider $provider, Request $request, IntegrationManager $manager): JsonResponse
    {
        $tenant = auth('api')->user()?->tenant;

        if ($tenant === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        DB::transaction(function () use ($provider, $tenant) {
            $provider->connections()->where('tenant_id', $tenant->id)->delete();
        });

        return response()->json(['message' => 'Integration disconnected successfully.']);
    }

    public function index()
    {
        $providers = IntegrationProvider::where('is_active', true)->get();

        return response()->json(IntegrationProviderResource::collection($providers));
    }
}
