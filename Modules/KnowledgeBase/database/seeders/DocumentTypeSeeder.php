<?php

namespace Modules\KnowledgeBase\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\KnowledgeBase\Models\DocumentType;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'name' => 'Policy',
                'description' => 'Define rules and compliance requirements',
            ],
            [
                'name' => 'Procedure',
                'description' => 'Step-by-step operational guidance',
            ],
            [
                'name' => 'FAQ',
                'description' => 'Answer common recurring questions',
            ],
            [
                'name' => 'Troubleshooting guide',
                'description' => 'Diagnose and resolve errors or exceptions',
            ],
        ];

        foreach ($types as $type) {
            DocumentType::updateOrCreate(
                ['name' => $type['name']],
                ['description' => $type['description']]
            );
        }
    }
}
