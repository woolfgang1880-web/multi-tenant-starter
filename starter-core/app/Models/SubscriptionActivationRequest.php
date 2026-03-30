<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Solicitud pública de activación (post-bloqueo trial / suscripción).
 * Sin lógica comercial: solo registro para revisión manual.
 */
class SubscriptionActivationRequest extends Model
{
    protected $fillable = [
        'tenant_codigo',
        'contact_email',
        'message',
        'ip_address',
        'user_agent',
    ];
}
