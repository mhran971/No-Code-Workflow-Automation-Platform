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
        Schema::create('integration_providers', function (Blueprint $table) {
            $table->string('id', 50)->primary(); // clickup, hubspot, etc.
            $table->string('name');
            $table->string('auth_type'); // oauth, api_key, etc.
            $table->json('config_schema'); // JSON schema for provider-specific config
            $table->json('auth_schema'); // JSON schema for provider-specific auth config
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_providers');
    }
};
