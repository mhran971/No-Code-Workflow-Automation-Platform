<?php

return [
    'name' => 'Integrations',

    'providers' => [
        'clickup' => [
            'label' => 'ClickUp',
            'driver' => \Modules\Integrations\Services\Drivers\ClickUpDriver::class,
            'client_id' => env('CLICKUP_CLIENT_ID'),
            'client_secret' => env('CLICKUP_CLIENT_SECRET'),
            'authorization_url' => 'https://app.clickup.com/api',
            'token_url' => 'https://api.clickup.com/api/v2/oauth/token',
            'team_url' => 'https://api.clickup.com/api/v2/team',
        ],
        'hubspot' => [
            'label' => 'HubSpot',
            'driver' => \Modules\Integrations\Services\Drivers\HubSpotDriver::class,
            'client_id' => env('HUBSPOT_CLIENT_ID'),
            'client_secret' => env('HUBSPOT_CLIENT_SECRET'),
            'scopes' => env('HUBSPOT_SCOPES'),
            'authorization_url' => 'https://app.hubspot.com/oauth/authorize',
            'token_url' => 'https://api.hubapi.com/oauth/v1/token',
        ],
        'google' => [
            'label' => 'Google',
            'driver' => \Modules\Integrations\Services\Drivers\GoogleDriver::class,
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'scopes' => 'https://www.googleapis.com/auth/gmail.readonly https://www.googleapis.com/auth/gmail.send https://www.googleapis.com/auth/documents https://www.googleapis.com/auth/spreadsheets https://www.googleapis.com/auth/drive.file',
            'authorization_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
        ],
    ],

    'actions' => [
        'send-email' => [
            'label' => 'Send Email',
            'driver' => \Modules\Integrations\Services\Actions\SendEmailAction::class,
        ],
    ],
];
