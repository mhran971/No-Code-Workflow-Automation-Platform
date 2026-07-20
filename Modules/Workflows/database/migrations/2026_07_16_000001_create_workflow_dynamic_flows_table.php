<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_dynamic_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('instance_id')->constrained('workflow_instances')->cascadeOnDelete();
            $table->foreignId('execution_id')->constrained('workflow_node_executions')->cascadeOnDelete();
            $table->string('node_key');
            $table->string('status')->default('awaiting_design');
            $table->json('definition')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('child_instance_id')->nullable()->constrained('workflow_instances')->nullOnDelete();
            $table->timestamps();

            $table->index(['instance_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::table('workflow_instances', function (Blueprint $table) {
            $table->boolean('is_dynamic')->default(false)->after('parent_execution_id');
            $table->foreignId('dynamic_flow_id')->nullable()->after('is_dynamic')
                ->constrained('workflow_dynamic_flows')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_instances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dynamic_flow_id');
            $table->dropColumn('is_dynamic');
        });

        Schema::dropIfExists('workflow_dynamic_flows');
    }
};
