<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `pending` instances (created when a tenant is over its concurrent-instance admission
 * cap — see InstanceAdmissionService / AdmitPendingInstancesCommand) are inserted with
 * started_at = null until they're promoted to running. The original migration never
 * allowed that, causing an integrity constraint violation on every dispatch that lands
 * a tenant in pending status (e.g. sub-workflow triggers).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_instances', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_instances', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable(false)->change();
        });
    }
};
