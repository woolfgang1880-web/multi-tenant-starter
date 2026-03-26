<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Observability\ReadinessService;
use Illuminate\Http\JsonResponse;

class ReadinessController extends Controller
{
    public function __invoke(ReadinessService $readiness): JsonResponse
    {
        $snapshot = $readiness->snapshot();

        return response()->json([
            'status' => $snapshot['status'],
            'checks' => $snapshot['checks'],
            'timestamp' => $snapshot['timestamp'],
        ], (int) $snapshot['http_status']);
    }
}

