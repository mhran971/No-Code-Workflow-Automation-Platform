<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Converts any legacy `expired` task statuses to `escalated` following
 * the replacement of the expired concept with automatic escalation.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('workflow_tasks')
            ->where('status', 'expired')
            ->update([
                'status' => 'escalated',
                'escalated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('workflow_tasks')
            ->where('status', 'escalated')
            ->whereNull('escalated_at')
            ->update(['status' => 'expired']);
    }
};
