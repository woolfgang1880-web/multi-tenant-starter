<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('operational_status', 20)->default('active')->after('activo');
            $table->timestamp('inactivated_at')->nullable()->after('operational_status');
            $table->foreignId('inactivated_by')->nullable()->after('inactivated_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reactivated_at')->nullable()->after('inactivated_by');
            $table->foreignId('reactivated_by')->nullable()->after('reactivated_at')
                ->constrained('users')->nullOnDelete();

            $table->index('operational_status');
        });

        DB::table('tenants')->update(['operational_status' => 'active']);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['inactivated_by']);
            $table->dropForeign(['reactivated_by']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['operational_status']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'operational_status',
                'inactivated_at',
                'inactivated_by',
                'reactivated_at',
                'reactivated_by',
            ]);
        });
    }
};
