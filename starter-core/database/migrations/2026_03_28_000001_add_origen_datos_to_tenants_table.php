<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tenants', 'origen_datos')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('origen_datos', 32)->nullable()->after('tipo_contribuyente');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'origen_datos')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('origen_datos');
            });
        }
    }
};
