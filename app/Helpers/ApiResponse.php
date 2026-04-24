<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function successResponse(
        mixed $data = null,
        string $message = 'Operacion realizada correctamente.',
        int $status = 200,
        array $meta = []
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => [],
            'meta' => array_merge([
                'timestamp' => now()->toIso8601String(),
            ], $meta),
        ], $status);
    }

    protected function errorResponse(
        string $message = 'No fue posible procesar la solicitud.',
        array $errors = [],
        int $status = 422,
        array $meta = []
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
            'meta' => array_merge([
                'timestamp' => now()->toIso8601String(),
            ], $meta),
        ], $status);
    }
}
