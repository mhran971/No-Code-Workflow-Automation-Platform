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
        if (Schema::hasColumn('documents', 'uploaded_at')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropColumn('uploaded_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->timestamp('uploaded_at')->nullable()->after('file_path');
        });
    }
};
