<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotente: corrige BDs donde existía user_sessions sin la columna tenant_id
 * (p. ej. migración 2026_03_26_100000 no ejecutada o entorno desincronizado).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_sessions')) {
            return;
        }

        if (Schema::hasColumn('user_sessions', 'tenant_id')) {
            return;
        }

        Schema::table('user_sessions', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
        });

        foreach (DB::table('user_sessions')->select('id', 'user_id')->cursor() as $row) {
            $tenantId = DB::table('users')->where('id', $row->user_id)->value('tenant_id');
            if ($tenantId !== null) {
                DB::table('user_sessions')->where('id', $row->id)->update(['tenant_id' => $tenantId]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_sessions') || ! Schema::hasColumn('user_sessions', 'tenant_id')) {
            return;
        }

        Schema::table('user_sessions', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
