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
        Schema::create('integration_connections', function (Blueprint $table) {
            $table->id();
            $table->string('integration_provider_id', 50);
            $table->foreign('integration_provider_id')
                ->references('id')
                ->on('integration_providers')
                ->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->json('auth_config'); // Store provider-specific auth config (e.g., tokens)
            $table->json('config'); // Store provider-specific config (e.g., workspace ID)
            $table->timestamps();

            $table->unique(['integration_provider_id', 'tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_connections');
    }
};
