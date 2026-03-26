<?php

namespace App\Support\Api;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function make(string $code, string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
