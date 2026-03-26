<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 1 — membresía N:N usuario↔empresa y contexto de tenant por sesión.
     *
     * - user_tenants: pertenencia explícita (prepara login sin tenant_codigo en fases posteriores).
     * - user_sessions.tenant_id: empresa activa elegida en el login (no muta users.tenant_id).
     * - users.is_platform_admin: reservado para super admin global (sin lógica de login aún).
     */
    public function up(): void
    {
        Schema::create('user_tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'tenant_id']);
            $table->index('tenant_id');
        });

        foreach (DB::table('users')->select('id', 'tenant_id')->cursor() as $row) {
            DB::table('user_tenants')->insert([
                'user_id' => $row->id,
                'tenant_id' => $row->tenant_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false)->after('activo');
        });

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
        Schema::table('user_sessions', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_platform_admin');
        });

        Schema::dropIfExists('user_tenants');
    }
};
