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
        Schema::table('workflow_nodes', function (Blueprint $table): void {
            $table->dropColumn(['is_entry_point', 'is_terminal']);
        });
    }

    public function down(): void
    {
        Schema::table('workflow_nodes', function (Blueprint $table): void {
            $table->boolean('is_entry_point')->default(false)->after('config');
            $table->boolean('is_terminal')->default(false)->after('is_entry_point');
        });
    }
};
