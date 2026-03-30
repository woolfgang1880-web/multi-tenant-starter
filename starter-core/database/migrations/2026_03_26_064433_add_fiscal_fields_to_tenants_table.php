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
        // La migración debe ser tolerante a estados parciales (entornos dev).
        if (! Schema::hasColumn('tenants', 'tipo_contribuyente')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('tipo_contribuyente', 32)->nullable()->after('subscription_status');
            });
        }

        if (! Schema::hasColumn('tenants', 'rfc')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('rfc', 13)->nullable()->after('tipo_contribuyente');
                $table->index('rfc');
            });
        }
        if (! Schema::hasColumn('tenants', 'nombre_fiscal')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('nombre_fiscal', 255)->nullable()->after('rfc');
            });
        }
        if (! Schema::hasColumn('tenants', 'nombre_comercial')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('nombre_comercial', 255)->nullable()->after('nombre_fiscal');
            });
        }

        if (! Schema::hasColumn('tenants', 'regimen_fiscal_principal')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('regimen_fiscal_principal', 64)->nullable()->after('nombre_comercial');
            });
        }
        if (! Schema::hasColumn('tenants', 'estatus_fiscal')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('estatus_fiscal', 64)->nullable()->after('regimen_fiscal_principal');
            });
        }
        if (! Schema::hasColumn('tenants', 'fecha_inicio_operaciones')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->date('fecha_inicio_operaciones')->nullable()->after('estatus_fiscal');
            });
        }

        if (! Schema::hasColumn('tenants', 'codigo_postal')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('codigo_postal', 10)->nullable()->after('fecha_inicio_operaciones');
            });
        }
        if (! Schema::hasColumn('tenants', 'tipo_vialidad')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('tipo_vialidad', 64)->nullable()->after('codigo_postal');
            });
        }
        if (! Schema::hasColumn('tenants', 'calle')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('calle', 255)->nullable()->after('tipo_vialidad');
            });
        }
        if (! Schema::hasColumn('tenants', 'numero_exterior')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('numero_exterior', 64)->nullable()->after('calle');
            });
        }
        if (! Schema::hasColumn('tenants', 'numero_interior')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('numero_interior', 64)->nullable()->after('numero_exterior');
            });
        }
        if (! Schema::hasColumn('tenants', 'colonia')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('colonia', 255)->nullable()->after('numero_interior');
            });
        }
        if (! Schema::hasColumn('tenants', 'localidad')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('localidad', 255)->nullable()->after('colonia');
            });
        }
        if (! Schema::hasColumn('tenants', 'municipio')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('municipio', 255)->nullable()->after('localidad');
            });
        }
        if (! Schema::hasColumn('tenants', 'estado')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('estado', 255)->nullable()->after('municipio');
            });
        }
        if (! Schema::hasColumn('tenants', 'entre_calle')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('entre_calle', 255)->nullable()->after('estado');
            });
        }
        if (! Schema::hasColumn('tenants', 'y_calle')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('y_calle', 255)->nullable()->after('entre_calle');
            });
        }

        if (! Schema::hasColumn('tenants', 'sat_qr_url')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->text('sat_qr_url')->nullable()->after('y_calle');
            });
        }
        if (! Schema::hasColumn('tenants', 'constancia_pdf_path')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('constancia_pdf_path', 1024)->nullable()->after('sat_qr_url');
            });
        }
        if (! Schema::hasColumn('tenants', 'constancia_imagen_path')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('constancia_imagen_path', 1024)->nullable()->after('constancia_pdf_path');
            });
        }
        if (! Schema::hasColumn('tenants', 'constancia_emitida_en')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->date('constancia_emitida_en')->nullable()->after('constancia_imagen_path');
            });
        }
        if (! Schema::hasColumn('tenants', 'constancia_id_cif')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('constancia_id_cif', 64)->nullable()->after('constancia_emitida_en');
            });
        }

        if (! Schema::hasColumn('tenants', 'curp')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('curp', 18)->nullable()->after('constancia_id_cif');
            });
        }
        if (! Schema::hasColumn('tenants', 'pf_nombre')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('pf_nombre', 255)->nullable()->after('curp');
            });
        }
        if (! Schema::hasColumn('tenants', 'pf_primer_apellido')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('pf_primer_apellido', 255)->nullable()->after('pf_nombre');
            });
        }
        if (! Schema::hasColumn('tenants', 'pf_segundo_apellido')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('pf_segundo_apellido', 255)->nullable()->after('pf_primer_apellido');
            });
        }

        if (! Schema::hasColumn('tenants', 'regimen_capital')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('regimen_capital', 255)->nullable()->after('pf_segundo_apellido');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $cols = [
            'tipo_contribuyente',
            'rfc',
            'nombre_fiscal',
            'nombre_comercial',
            'regimen_fiscal_principal',
            'estatus_fiscal',
            'fecha_inicio_operaciones',
            'codigo_postal',
            'tipo_vialidad',
            'calle',
            'numero_exterior',
            'numero_interior',
            'colonia',
            'localidad',
            'municipio',
            'estado',
            'entre_calle',
            'y_calle',
            'sat_qr_url',
            'constancia_pdf_path',
            'constancia_imagen_path',
            'constancia_emitida_en',
            'constancia_id_cif',
            'curp',
            'pf_nombre',
            'pf_primer_apellido',
            'pf_segundo_apellido',
            'regimen_capital',
        ];

        $existing = array_values(array_filter($cols, fn (string $c) => Schema::hasColumn('tenants', $c)));
        if ($existing !== []) {
            Schema::table('tenants', function (Blueprint $table) use ($existing) {
                $table->dropColumn($existing);
            });
        }
    }
};
