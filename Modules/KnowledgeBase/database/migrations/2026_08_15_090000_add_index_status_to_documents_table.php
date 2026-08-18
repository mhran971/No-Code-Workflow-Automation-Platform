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
        Schema::table('documents', function (Blueprint $table) {
            $table->string('index_status')->default('pending')->after('is_active');
            $table->text('index_error')->nullable()->after('index_status');
            $table->unsignedInteger('chunks_count')->nullable()->after('index_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['index_status', 'index_error', 'chunks_count']);
        });
    }
};
