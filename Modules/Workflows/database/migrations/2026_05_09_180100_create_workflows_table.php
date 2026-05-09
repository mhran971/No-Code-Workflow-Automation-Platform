<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->restrictOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('workflow_templates')->nullOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('disabled')->index();
            $table->json('draft_definition');
            $table->unsignedInteger('draft_revision')->default(1);
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->unsignedInteger('current_version_number')->default(0);
            $table->string('current_version_label')->nullable();
            $table->unsignedInteger('total_runs')->default(0);
            $table->unsignedInteger('active_instances')->default(0);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'team_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
