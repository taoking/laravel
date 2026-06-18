<?php

namespace App\Support\Response;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'success', int $status = 200): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function created(mixed $data = null, string $message = 'created'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    public static function noContent(string $message = 'success'): JsonResponse
    {
        return self::success(null, $message);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    public static function error(int $code, string $message, array $errors = [], int $status = 400): JsonResponse
    {
        $payload = [
            'code' => $code,
            'message' => $message,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * @param  array<int, mixed>  $items
     */
    public static function paginated(LengthAwarePaginator $paginator, array $items, string $message = 'success'): JsonResponse
    {
        return self::success([
            'items' => $items,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'page_size' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], $message);
    }
}
