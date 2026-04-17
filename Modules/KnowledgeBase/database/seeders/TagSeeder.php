<?php

namespace Modules\KnowledgeBase\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\Models\Tenant;
use Modules\KnowledgeBase\Models\Tag;

class TagSeeder extends Seeder
{
    /**
     * Seed tags for the first tenant (for testing).
     */
    public function run(): void
    {
        $tenant = Tenant::query()->first();

        if (! $tenant) {
            return;
        }

        $tags = [
            'Getting Started',
            'API',
            'Workflows',
            'Troubleshooting',
        ];

        foreach ($tags as $name) {
            Tag::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $name,
                ],
                []
            );
        }
    }
}
