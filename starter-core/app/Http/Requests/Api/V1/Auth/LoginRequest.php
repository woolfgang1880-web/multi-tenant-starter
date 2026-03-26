<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ruta pública: no extiende ApiFormRequest (sin tenant en contexto).
 */
final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** Opcional: si se omite o va vacío, login global (Fase 2). Si se envía, mismo comportamiento que antes. */
            'tenant_codigo' => ['nullable', 'string', 'max:64'],
            'usuario' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
