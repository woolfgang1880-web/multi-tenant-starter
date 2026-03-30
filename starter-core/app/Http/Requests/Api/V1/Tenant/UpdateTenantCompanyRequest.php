<?php

namespace App\Http\Requests\Api\V1\Tenant;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Actualización parcial de datos editables de empresa (Tenant).
 * Elimina campos inmutables del request antes de validar.
 */
final class UpdateTenantCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->tenantIdForRules() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'codigo',
            'nombre',
            'rfc',
            'nombre_fiscal',
            'activo',
            'subscription_status',
            'trial_starts_at',
            'trial_ends_at',
            'operational_status',
            'inactivated_at',
            'inactivated_by',
            'reactivated_at',
            'reactivated_by',
        ] as $k) {
            $this->request->remove($k);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->tenantIdForRules();
        if ($tenantId === null) {
            return [];
        }

        $genericRfcs = ['XAXX010101000', 'XEXX010101000'];

        $pfCodes = array_map('strval', array_keys(config('regimenes_fiscal.persona_fisica', [])));
        $pmCodes = array_map('strval', array_keys(config('regimenes_fiscal.persona_moral', [])));

        return [
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('tenants', 'slug')->ignore($tenantId)],

            'origen_datos' => ['sometimes', 'string', Rule::in(['sat_url', 'pdf', 'imagen_qr', 'manual'])],

            'tipo_contribuyente' => ['sometimes', 'string', Rule::in(['persona_fisica', 'persona_moral'])],

            'regimen_fiscal_principal' => [
                'sometimes',
                'string',
                'regex:/^\d{3}$/',
                function (string $attribute, mixed $value, \Closure $fail) use ($pfCodes, $pmCodes) {
                    $tipo = $this->input('tipo_contribuyente', $this->resolveTenantRecord()?->tipo_contribuyente);
                    if ($tipo === null || $tipo === '') {
                        return;
                    }
                    $allowed = $tipo === 'persona_fisica' ? $pfCodes : $pmCodes;
                    if (! in_array((string) $value, $allowed, true)) {
                        $fail('Datos fiscales: el régimen no es válido para el tipo de contribuyente.');
                    }
                },
            ],

            'codigo_postal' => ['sometimes', 'string', 'max:10'],
            'tipo_vialidad' => ['sometimes', 'nullable', 'string', 'max:64'],
            'calle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'numero_exterior' => ['sometimes', 'nullable', 'string', 'max:64'],
            'numero_interior' => ['sometimes', 'nullable', 'string', 'max:64'],
            'colonia' => ['sometimes', 'nullable', 'string', 'max:255'],
            'localidad' => ['sometimes', 'nullable', 'string', 'max:255'],
            'municipio' => ['sometimes', 'nullable', 'string', 'max:255'],
            'estado' => ['sometimes', 'string', 'max:255'],
            'correo_electronico' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],

            'curp' => ['sometimes', 'nullable', 'string', 'max:18'],
            'pf_nombre' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pf_primer_apellido' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pf_segundo_apellido' => ['sometimes', 'nullable', 'string', 'max:255'],

            'nombre_comercial' => ['sometimes', 'nullable', 'string', 'max:255'],
            'estatus_fiscal' => ['sometimes', 'nullable', 'string', 'max:64'],
            'fecha_inicio_operaciones' => ['sometimes', 'nullable', 'date'],
            'entre_calle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'y_calle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sat_qr_url' => ['sometimes', 'nullable', 'string'],
            'constancia_pdf_path' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'constancia_imagen_path' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'constancia_emitida_en' => ['sometimes', 'nullable', 'date'],
            'constancia_id_cif' => ['sometimes', 'nullable', 'string', 'max:64'],
            'regimen_capital' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    private function tenantIdForRules(): ?int
    {
        $codigo = $this->route('tenant_codigo');
        if ($codigo !== null) {
            return Tenant::query()->where('codigo', $codigo)->value('id');
        }

        return current_tenant_id();
    }

    private function resolveTenantRecord(): ?Tenant
    {
        $id = $this->tenantIdForRules();

        return $id === null ? null : Tenant::query()->find($id);
    }
}
