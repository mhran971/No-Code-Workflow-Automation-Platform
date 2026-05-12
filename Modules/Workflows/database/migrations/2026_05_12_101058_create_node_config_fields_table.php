<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_config_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained('nodes')->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('type');
            $table->string('placeholder')->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->json('default_value')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['node_id', 'key']);
            $table->index(['node_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_config_fields');
    }
};
