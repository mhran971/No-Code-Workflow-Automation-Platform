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
            'description' => 'ClickUp is a productivity platform that provides tools for project management, document collaboration, spreadsheets, goal tracking, and more. It helps teams organize their work and collaborate effectively.',
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
            'description' => 'HubSpot is a leading CRM platform that offers a suite of tools for marketing, sales, customer service, and content management. It helps businesses attract, engage, and delight customers by providing a unified platform for managing customer relationships and automating marketing and sales processes.',
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
            'description' => 'Google offers a wide range of services and APIs that allow developers to integrate with their ecosystem. This includes services like Google Drive, Google Calendar, Gmail, and more. Integrating with Google can help automate workflows and enhance productivity by leveraging the power of Google\'s services.',
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
