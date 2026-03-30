<?php

namespace App\Http\Controllers\Api\V1\Subscription;

use App\Http\Controllers\Controller;
use App\Services\Subscription\SubscriptionActivationRequestService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SubscriptionRequestActivationController extends Controller
{
    public function __invoke(Request $request, SubscriptionActivationRequestService $svc): JsonResponse
    {
        $validated = $request->validate([
            'tenant_codigo' => ['nullable', 'string', 'max:64'],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $svc->create([
            'tenant_codigo' => $validated['tenant_codigo'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'message' => $validated['message'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
        ]);

        return ApiResponse::make('OK', 'Solicitud registrada.', [
            'received' => true,
        ], 201);
    }
}
