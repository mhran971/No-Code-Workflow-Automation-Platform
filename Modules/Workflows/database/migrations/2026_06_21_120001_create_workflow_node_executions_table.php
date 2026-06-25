<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unified runtime-state table: every non-terminal row is an active token; `merge` rows carry their own
 * join arrival counters; waiting rows carry their own wake-up time. Subsumes the would-be
 * workflow_execution_tokens / workflow_joins / workflow_timers tables.
 * See Modules/Workflows/docs/execution-engine/SCHEMA.md §2.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_node_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('workflow_instances')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('node_key');
            $table->string('node_type');
            $table->string('status')->default('pending'); // NodeExecutionStatus
            $table->unsignedInteger('attempt')->default(1);
            $table->string('idempotency_key')->unique();

            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->json('error')->nullable();

            // token lineage
            $table->foreignId('parent_execution_id')->nullable()
                ->constrained('workflow_node_executions')->nullOnDelete();
            $table->string('fork_group')->nullable();

            // join state (set only on `merge` rows)
            $table->unsignedInteger('expected_count')->nullable();
            $table->unsignedInteger('arrived_count')->default(0);

            // durable wait / timer
            $table->timestamp('wait_until')->nullable();
            $table->string('wait_type')->nullable(); // WaitType

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['instance_id', 'node_key', 'attempt']);
            $table->index(['instance_id', 'status']);
            $table->index(['status', 'wait_until']); // timer scan
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_node_executions');
    }
};
