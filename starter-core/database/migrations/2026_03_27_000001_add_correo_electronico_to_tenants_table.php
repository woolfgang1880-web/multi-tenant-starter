<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tenants', 'correo_electronico')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('correo_electronico', 255)->nullable()->after('estado');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'correo_electronico')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('correo_electronico');
            });
        }
    }
};
