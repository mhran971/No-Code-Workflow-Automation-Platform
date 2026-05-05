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
        IntegrationProvider::updateOrCreate(['id' => 'clickup'], [
            'id' => 'clickup',
            'name' => 'ClickUp',
            'auth_type' => 'oauth2',
            'config_schema' => [
                'teams' => [
                    'type' => 'array',
                    'required' => true,
                ],
            ],
            'auth_schema' => [
                'access_token' => [
                    'type' => 'string',
                    'required' => true,
                ],
            ],
            'is_active' => true,
        ]);

        IntegrationProvider::updateOrCreate(['id' => 'hubspot'], [
            'id' => 'hubspot',
            'name' => 'HubSpot',
            'auth_type' => 'oauth2',
            'config_schema' => [
                'portal_id' => [
                    'type' => 'string',
                    'required' => false,
                ],
            ],
            'auth_schema' => [
                'access_token' => [
                    'type' => 'string',
                    'required' => true,
                ],
                'refresh_token' => [
                    'type' => 'string',
                    'required' => false,
                ],
                'expires_at' => [
                    'type' => 'string',
                    'required' => false,
                ],
            ],
            'is_active' => true,
        ]);

        IntegrationProvider::updateOrCreate(['id' => 'google'], [
            'id' => 'google',
            'name' => 'Google',
            'auth_type' => 'oauth2',
            'config_schema' => [],
            'auth_schema' => [
                'access_token' => [
                    'type' => 'string',
                    'required' => true,
                ],
                'refresh_token' => [
                    'type' => 'string',
                    'required' => false,
                ],
                'expires_at' => [
                    'type' => 'string',
                    'required' => false,
                ],
            ],
            'is_active' => true,
        ]);
    }
}
