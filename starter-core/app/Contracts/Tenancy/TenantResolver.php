<?php

namespace App\Contracts\Tenancy;

use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * Contrato para resolver el tenant activo a partir del request (o contexto HTTP).
 * Implementaciones futuras: subdominio, cabecera, dominio, cadena de estrategias.
 */
interface TenantResolver
{
    public function resolve(?Request $request = null): ?Tenant;
}
