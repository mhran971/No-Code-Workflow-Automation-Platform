<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('version_label');
            $table->json('definition'); // TODO: remove the difinition and make a seperate table instead
            $table->text('release_note')->nullable();
            $table->foreignId('published_by_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['workflow_id', 'version_number']);
            $table->unique(['workflow_id', 'version_label']);
            $table->index(['tenant_id', 'published_at']);
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->foreign('current_version_id')
                ->references('id')
                ->on('workflow_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });

        Schema::dropIfExists('workflow_versions');
    }
};
