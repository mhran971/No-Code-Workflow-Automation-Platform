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
        Schema::create('workflow_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('node_id')->constrained('nodes');
            $table->foreignId('version_id')->nullable()->constrained('workflow_versions')->nullOnDelete();
            $table->string('key');  // unique per workflow+version
            $table->string('label');
            $table->json('config')->nullable();
            $table->boolean('is_entry_point')->default(false);
            $table->boolean('is_terminal')->default(false); // revise
            $table->timestamps();

            $table->unique(['workflow_id', 'version_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_nodes');
    }
};
