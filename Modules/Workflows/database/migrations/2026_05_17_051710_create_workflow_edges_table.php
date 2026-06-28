<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflow_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('version_id')->nullable()->constrained('workflow_versions')->nullOnDelete();

            // Source
            $table->string('source_node_key');
            $table->string('source_handle')->nullable(); // visual handle on the node

            // Target
            $table->string('target_node_key');
            $table->string('target_handle')->nullable();

            // Branch type
            $table->enum('branch_type', ['default', 'conditional', 'parallel'])->default('default');

            // Conditional branch fields
            // Expression string e.g. "order.amount > 1000 && user.role == 'manager'"
            $table->text('condition_expression')->nullable();
            $table->boolean('is_default_branch')->default(false); // fallback if no condition matches

            // Parallel branch fields
            // Groups edges that fork together from the same source
            $table->string('parallel_group_key')->nullable(); // e.g. "parallel_1"
            $table->enum('parallel_strategy', ['fork_join', 'fire_and_forget'])->nullable();
            // For fork_join: which node is the join point
            $table->string('join_node_key')->nullable();

            // Ordering: which conditional branch to evaluate first
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index(['workflow_id', 'version_id']);
            $table->index(['workflow_id', 'parallel_group_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_edges');
    }
};
