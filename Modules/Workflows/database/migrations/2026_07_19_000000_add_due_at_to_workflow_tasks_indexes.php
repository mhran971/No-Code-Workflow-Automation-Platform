<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_tasks', function (Blueprint $table) {
            $table->dropIndex(['assignee_id', 'status']);
            $table->dropIndex(['tenant_id', 'status']);
            $table->index(['tenant_id', 'assignee_id', 'status', 'due_at']);
            $table->index(['tenant_id', 'status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('workflow_tasks', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'assignee_id', 'status', 'due_at']);
            $table->dropIndex(['tenant_id', 'status', 'due_at']);
            $table->index(['assignee_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });
    }
};
