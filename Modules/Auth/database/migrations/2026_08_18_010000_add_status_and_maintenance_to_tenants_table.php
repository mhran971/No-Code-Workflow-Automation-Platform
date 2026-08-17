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
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('business_type')->index();
            $table->boolean('maintenance_mode')->default(false)->after('is_active');
            $table->text('maintenance_message')->nullable()->after('maintenance_mode');
            $table->timestamp('deactivated_at')->nullable()->after('maintenance_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropColumn([
                'is_active',
                'maintenance_mode',
                'maintenance_message',
                'deactivated_at',
            ]);
        });
    }
};
