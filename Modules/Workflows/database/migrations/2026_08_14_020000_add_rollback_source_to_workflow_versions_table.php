<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_versions', function (Blueprint $table) {
            $table->unsignedBigInteger('rollback_source_version_id')->nullable()->after('published_at');

            $table->foreign('rollback_source_version_id')
                ->references('id')
                ->on('workflow_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_versions', function (Blueprint $table) {
            $table->dropForeign(['rollback_source_version_id']);
            $table->dropColumn('rollback_source_version_id');
        });
    }
};
