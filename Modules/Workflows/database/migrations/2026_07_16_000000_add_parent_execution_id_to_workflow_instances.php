<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_instances', function (Blueprint $table) {
            $table->foreignId('parent_execution_id')->nullable()->after('parent_instance_id')
                ->constrained('workflow_node_executions')->nullOnDelete();

            $table->index('parent_execution_id');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_instances', function (Blueprint $table) {
            $table->dropIndex(['parent_execution_id']);
            $table->dropConstrainedForeignId('parent_execution_id');
        });
    }
};
