<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Workflows\Models\Workflow;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->uuid('public_token')->nullable()->after('id');
        });

        Workflow::query()->whereNull('public_token')->each(
            fn (Workflow $workflow) => $workflow->forceFill(['public_token' => (string) Str::uuid()])->save()
        );

        Schema::table('workflows', function (Blueprint $table) {
            $table->unique('public_token');
        });
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn('public_token');
        });
    }
};
