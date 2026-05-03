<?php

namespace Modules\Integrations\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Integrations\Models\IntegrationProvider;

class IntegrationsDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        IntegrationProvider::create([
            'id' => 'clickup',
            'name' => 'ClickUp',
            'auth_type' => 'oauth2',
            'config_schema' => json_encode([
                "teams"=> ["type"=> "array"]
            ]),
            'auth_schema' => json_encode([
                'access_token' => [
                    'type' => 'string',
                ],
            ]),
            'is_active' => true,
        ]);

        IntegrationProvider::create([
            'id' => 'hubspot',
            'name' => 'HubSpot',
            'auth_type' => 'oauth2',
            'config_schema' => json_encode([
                'portal_id' => ['type' => 'string'],
            ]),
            'auth_schema' => json_encode([
                'access_token' => ['type' => 'string'],
                'refresh_token' => ['type' => 'string'],
                'expires_at' => ['type' => 'string'],
            ]),
            'is_active' => true,
        ]);
    }
}
