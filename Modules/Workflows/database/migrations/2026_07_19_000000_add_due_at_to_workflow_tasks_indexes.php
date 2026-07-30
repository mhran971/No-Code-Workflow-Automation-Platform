<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $index): bool
    {
        return Schema::hasIndex($table, $index);
    }

    public function up(): void
    {
        Schema::table('workflow_tasks', function (Blueprint $table) {
            if ($this->indexExists('workflow_tasks', 'workflow_tasks_assignee_id_status_index')) {
                $table->index(['assignee_id', 'status', 'due_at']);
                $table->dropIndex(['assignee_id', 'status']);
            }

            if ($this->indexExists('workflow_tasks', 'workflow_tasks_tenant_id_status_index')) {
                $table->index(['tenant_id', 'status', 'due_at']);
                $table->dropIndex(['tenant_id', 'status']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('workflow_tasks', function (Blueprint $table) {
            if ($this->indexExists('workflow_tasks', 'workflow_tasks_assignee_id_status_due_at_index')) {
                $table->index(['assignee_id', 'status']);
                $table->dropIndex(['assignee_id', 'status', 'due_at']);
            }

            if ($this->indexExists('workflow_tasks', 'workflow_tasks_tenant_id_status_due_at_index')) {
                $table->index(['tenant_id', 'status']);
                $table->dropIndex(['tenant_id', 'status', 'due_at']);
            }
        });
    }
};
