<?php

namespace Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integrations\Exceptions\IntegrationException;
use Modules\Integrations\Models\IntegrationConnection;
use Modules\Integrations\Services\ClickUp\ClickUpClient;

class ClickUpController extends Controller
{
    public function workspaces(Request $request, ClickUpClient $client): JsonResponse
    {
        try {
            $connection = $this->connectionForCurrentTenant();

            return response()->json(['data' => $client->listWorkspaces($connection)]);
        } catch (IntegrationException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status());
        }
    }

    public function lists(string $workspaceId, Request $request, ClickUpClient $client): JsonResponse
    {
        try {
            $connection = $this->connectionForCurrentTenant();

            return response()->json(['data' => $client->listLists($connection, $workspaceId)]);
        } catch (IntegrationException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status());
        }
    }

    private function connectionForCurrentTenant(): IntegrationConnection
    {
        $tenant = auth('api')->user()?->tenant;

        if ($tenant === null) {
            throw new IntegrationException('Unauthenticated.', 401);
        }

        $connection = IntegrationConnection::query()
            ->where('integration_provider_id', 'clickup')
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($connection === null) {
            throw IntegrationException::connectionNotFound('clickup');
        }

        return $connection;
    }
}
