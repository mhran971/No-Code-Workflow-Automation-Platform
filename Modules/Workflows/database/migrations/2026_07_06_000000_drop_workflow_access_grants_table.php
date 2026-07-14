<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('workflow_access_grants');
    }

    public function down(): void
    {
        Schema::create('workflow_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('access_level')->default('view');
            $table->foreignId('granted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at')->useCurrent();

            $table->unique(['workflow_id', 'user_id']);
            $table->index(['user_id', 'access_level']);
        });
    }
};
