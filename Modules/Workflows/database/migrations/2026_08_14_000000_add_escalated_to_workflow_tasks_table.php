<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_tasks', function (Blueprint $table) {
            $table->timestamp('escalated_at')->nullable()->after('completed_at');
            $table->index(['tenant_id', 'status', 'escalated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('workflow_tasks', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status', 'escalated_at']);
            $table->dropColumn('escalated_at');
        });
    }
};
