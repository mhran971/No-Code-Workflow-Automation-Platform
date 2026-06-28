<?php

return [
    'name' => 'Integrations',

    'providers' => [
        'clickup' => [
            'label' => 'ClickUp',
            'driver' => \Modules\Integrations\Services\Drivers\ClickUpDriver::class,
            'client_id' => env('CLICKUP_CLIENT_ID', 'CL768XNQAFNGVH0YJB9O4I3L84QOPJC9'),
            'client_secret' => env('CLICKUP_CLIENT_SECRET', 'Z4XQ29JW4T0CH4PYZAK59DSJQCYZ3M0KNCDWQ8ACNFB9KTL8DHB8T3T2NB5L0S8B'),
            'authorization_url' => 'https://app.clickup.com/api',
            'token_url' => 'https://api.clickup.com/api/v2/oauth/token',
            'team_url' => 'https://api.clickup.com/api/v2/team',
        ],
        'hubspot' => [
            'label' => 'HubSpot',
            'driver' => \Modules\Integrations\Services\Drivers\HubSpotDriver::class,
            'client_id' => env('HUBSPOT_CLIENT_ID', '3438ff55-90e3-4a76-9db5-a50ef5e8a3df'),
            'client_secret' => env('HUBSPOT_CLIENT_SECRET', '41b0254c-07f3-43b8-bf41-419786bf8e85'),
            'scopes' => env('HUBSPOT_SCOPES', 'crm.objects.contacts.read crm.objects.contacts.write'),
            'authorization_url' => 'https://app.hubspot.com/oauth/authorize',
            'token_url' => 'https://api.hubapi.com/oauth/v1/token',
        ],
        'google' => [
            'label' => 'Google',
            'driver' => \Modules\Integrations\Services\Drivers\GoogleDriver::class,
            'client_id' => env('GOOGLE_CLIENT_ID', '992359647999-5uph99aurtdiivnbc3ealracma2cqrnd.apps.googleusercontent.com'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET', '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'),
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
