<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only lifecycle/audit log. One row per transition.
 * See Modules/Workflows/docs/execution-engine/SCHEMA.md §2.4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('workflow_instances')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('node_key')->nullable();
            $table->string('type');
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable(); // append-only; no updated_at

            $table->index(['instance_id', 'created_at']);
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_events');
    }
};
