<?php

namespace Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

            return response()->json(['auth_url' => $authUrl], 200);
        } catch (IntegrationException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status());
        }
    }

    public function callback(Request $request, IntegrationManager $manager): RedirectResponse|JsonResponse|View
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);
        try {
            $connection = $manager->callback($validated['state'], $validated['code']);

            return redirect("http://localhost:5173/dashboard/integrations");
            // return view('integrations::callback-success', [
            //     'appName' => config('app.name', 'Laravel'),
            //     'connection' => $connection,
            // ]);
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
