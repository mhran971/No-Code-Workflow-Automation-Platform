<?php

namespace Modules\Workflows\Database\Seeders;

use Illuminate\Database\Seeder;

class WorkflowsDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            'Modules\\Workflows\\Database\\Seeders\\NodeDefinitionSeeder',
            'Modules\\Workflows\\Database\\Seeders\\WorkflowTemplateSeeder',
        ]);
    }
}
