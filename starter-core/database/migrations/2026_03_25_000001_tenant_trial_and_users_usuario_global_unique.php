<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 1 (evolución multi-tenant):
     * - unicidad global de `users.usuario` (login único en toda la plataforma)
     * - columnas de suscripción/prueba en `tenants` (sin enforcement aún; prepara fases posteriores)
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('trial_starts_at')->nullable()->after('activo');
            $table->timestamp('trial_ends_at')->nullable()->after('trial_starts_at');
            $table->string('subscription_status', 32)->nullable()->after('trial_ends_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'usuario']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('usuario');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['usuario']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique(['tenant_id', 'usuario']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['trial_starts_at', 'trial_ends_at', 'subscription_status']);
        });
    }
};
