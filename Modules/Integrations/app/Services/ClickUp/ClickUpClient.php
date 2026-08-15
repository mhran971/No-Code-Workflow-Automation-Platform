<?php

namespace Modules\Integrations\Services\ClickUp;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Modules\Integrations\Exceptions\IntegrationException;
use Modules\Integrations\Models\IntegrationConnection;

/**
 * Authenticated calls to the ClickUp REST API on behalf of a stored IntegrationConnection.
 * ClickUp OAuth tokens don't expire, so unlike GmailClient there's no refresh flow.
 */
class ClickUpClient
{
    private const API_BASE = 'https://api.clickup.com/api/v2';

    /**
     * @return list<array{id: string, name: string}>
     */
    public function listWorkspaces(IntegrationConnection $connection): array
    {
        $response = $this->get($connection, (string) config('integrations.providers.clickup.team_url'));

        return array_map(
            fn (array $team): array => ['id' => (string) $team['id'], 'name' => (string) $team['name']],
            $response['teams'] ?? [],
        );
    }

    /**
     * Aggregates every List reachable from a Workspace: Lists inside Folders, plus
     * folderless Lists directly under a Space. Requires a Space -> Folder -> List
     * fan-out since ClickUp has no single "all lists in a workspace" endpoint.
     *
     * @return list<array{id: string, name: string, space: string, folder: ?string}>
     */
    public function listLists(IntegrationConnection $connection, string $workspaceId): array
    {
        $spaces = $this->get($connection, self::API_BASE."/team/{$workspaceId}/space?archived=false")['spaces'] ?? [];

        $lists = [];

        foreach ($spaces as $space) {
            $spaceId = (string) $space['id'];
            $spaceName = (string) $space['name'];

            $folderless = $this->get($connection, self::API_BASE."/space/{$spaceId}/list?archived=false")['lists'] ?? [];
            foreach ($folderless as $list) {
                $lists[] = ['id' => (string) $list['id'], 'name' => (string) $list['name'], 'space' => $spaceName, 'folder' => null];
            }

            $folders = $this->get($connection, self::API_BASE."/space/{$spaceId}/folder?archived=false")['folders'] ?? [];
            foreach ($folders as $folder) {
                $folderId = (string) $folder['id'];
                $folderName = (string) $folder['name'];

                $folderLists = $this->get($connection, self::API_BASE."/folder/{$folderId}/list?archived=false")['lists'] ?? [];
                foreach ($folderLists as $list) {
                    $lists[] = ['id' => (string) $list['id'], 'name' => (string) $list['name'], 'space' => $spaceName, 'folder' => $folderName];
                }
            }
        }

        return $lists;
    }

    /**
     * @return array<string, mixed> The created task, as returned by ClickUp.
     */
    public function createTask(IntegrationConnection $connection, string $listId, string $name, ?string $markdownContent): array
    {
        $token = $this->accessToken($connection);

        $body = ['name' => $name];
        if ($markdownContent !== null) {
            $body['markdown_content'] = $markdownContent;
        }

        $response = Http::withToken($token)->post(self::API_BASE."/list/{$listId}/task", $body);

        return $this->jsonOrFail($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function get(IntegrationConnection $connection, string $url): array
    {
        $response = Http::withToken($this->accessToken($connection))->get($url);

        return $this->jsonOrFail($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonOrFail(Response $response): array
    {
        if ($response->failed()) {
            $detail = $response->json('err') ?? $response->body();
            throw IntegrationException::apiCallFailed('clickup', (string) $detail, $response->status());
        }

        return $response->json();
    }

    private function accessToken(IntegrationConnection $connection): string
    {
        return Crypt::decryptString($connection->auth_config['access_token']);
    }
}
