<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive extension of workflow_instances for the execution engine.
 * See Modules/Workflows/docs/execution-engine/SCHEMA.md §2.1 — columns are added, never removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_instances', function (Blueprint $table) {
            $table->foreignId('parent_instance_id')->nullable()->after('workflow_version_id')
                ->constrained('workflow_instances')->nullOnDelete(); // reserved: sub-workflow (Phase 3)
            $table->string('trigger_type')->default('manual')->after('status'); // TriggerType
            $table->string('correlation_id')->nullable()->after('trigger_type'); // idempotency / external delivery id
            $table->json('context')->nullable()->after('payload');  // runtime variable store
            $table->json('error')->nullable()->after('context');    // failure detail when status=failed
            $table->string('paused_reason')->nullable()->after('error');

            $table->index(['tenant_id', 'correlation_id']);
        });
    }

    public function down(): void
    {
        Schema::table('workflow_instances', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'correlation_id']);
            $table->dropConstrainedForeignId('parent_instance_id');
            $table->dropColumn(['trigger_type', 'correlation_id', 'context', 'error', 'paused_reason']);
        });
    }
};
