<?php

namespace App\Http\Requests\Api\V1\Platform;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('rfc')) {
            $this->merge([
                'rfc' => strtoupper(trim((string) $this->input('rfc'))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $genericRfcs = ['XAXX010101000', 'XEXX010101000'];

        $pfCodes = array_map('strval', array_keys(config('regimenes_fiscal.persona_fisica', [])));
        $pmCodes = array_map('strval', array_keys(config('regimenes_fiscal.persona_moral', [])));

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'codigo' => ['required', 'string', 'max:64', Rule::unique('tenants', 'codigo')],
            'activo' => ['sometimes', 'boolean'],

            'origen_datos' => ['required', 'string', Rule::in(['sat_url', 'pdf', 'imagen_qr', 'manual'])],

            'tipo_contribuyente' => ['required', 'string', Rule::in(['persona_fisica', 'persona_moral'])],

            'rfc' => ['required', 'string', 'max:13', Rule::notIn($genericRfcs)],
            'nombre_fiscal' => ['nullable', 'string', 'max:255', 'required_if:tipo_contribuyente,persona_moral'],

            'regimen_fiscal_principal' => [
                'required',
                'string',
                'regex:/^\d{3}$/',
                function (string $attribute, mixed $value, \Closure $fail) use ($pfCodes, $pmCodes) {
                    $tipo = $this->input('tipo_contribuyente');
                    $allowed = $tipo === 'persona_fisica' ? $pfCodes : $pmCodes;
                    if (! in_array((string) $value, $allowed, true)) {
                        $fail('Datos fiscales: el régimen no es válido para el tipo de contribuyente.');
                    }
                },
            ],

            'codigo_postal' => ['required', 'string', 'max:10'],
            'tipo_vialidad' => ['nullable', 'string', 'max:64'],
            'calle' => ['nullable', 'string', 'max:255'],
            'numero_exterior' => ['nullable', 'string', 'max:64'],
            'numero_interior' => ['nullable', 'string', 'max:64'],
            'colonia' => ['nullable', 'string', 'max:255'],
            'localidad' => ['nullable', 'string', 'max:255'],
            'municipio' => ['nullable', 'string', 'max:255', 'required_if:tipo_contribuyente,persona_fisica'],
            'estado' => ['required', 'string', 'max:255'],
            'correo_electronico' => ['nullable', 'string', 'email', 'max:255'],

            'curp' => ['nullable', 'string', 'max:18', 'required_if:tipo_contribuyente,persona_fisica'],
            'pf_nombre' => ['nullable', 'string', 'max:255', 'required_if:tipo_contribuyente,persona_fisica'],
            'pf_primer_apellido' => ['nullable', 'string', 'max:255'],
            'pf_segundo_apellido' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $rfc = strtoupper(trim((string) $this->input('rfc', '')));
            if ($rfc === '') {
                return;
            }

            $exists = Tenant::query()
                ->where('rfc', $rfc)
                ->where('operational_status', Tenant::OPERATIONAL_ACTIVE)
                ->exists();

            if ($exists) {
                $validator->errors()->add(
                    'rfc',
                    'Ya existe una empresa operativa activa con este RFC.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'tipo_contribuyente.required' => 'Datos fiscales: el tipo de contribuyente es obligatorio.',
            'rfc.required' => 'Datos fiscales: RFC es obligatorio.',
            'rfc.not_in' => 'RFC genérico no permitido para emisor fiscal.',
            'nombre_fiscal.required_if' => 'Persona moral: Denominación/Razón Social es obligatoria.',
            'regimen_fiscal_principal.required' => 'Datos fiscales: Régimen fiscal principal es obligatorio.',
            'regimen_fiscal_principal.regex' => 'Datos fiscales: el régimen debe ser una clave de 3 dígitos.',
            'curp.required_if' => 'Persona física: CURP es obligatorio.',
            'pf_nombre.required_if' => 'Persona física: Nombre es obligatorio.',
            'estado.required' => 'Domicilio fiscal: Entidad federativa es obligatoria.',
            'municipio.required_if' => 'Domicilio fiscal: Municipio o delegación es obligatorio para persona física.',
            'codigo_postal.required' => 'Domicilio fiscal: Código postal es obligatorio.',
            'correo_electronico.email' => 'Domicilio fiscal: Correo electrónico tiene formato inválido.',
            'origen_datos.required' => 'Datos fiscales: el origen de datos es obligatorio.',
            'origen_datos.in' => 'Datos fiscales: origen de datos no válido.',
        ];
    }
}
