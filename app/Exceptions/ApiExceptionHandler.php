<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders a consistent JSON error envelope for /v1 endpoints:
 *   { "error": { "code": "...", "message": "...", "details"?: {...} } }
 */
class ApiExceptionHandler
{
    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('v1/*')) {
            return null;
        }

        [$status, $code, $message, $details] = self::map($e);

        $error = ['code' => $code, 'message' => $message];
        if ($details !== null) {
            $error['details'] = $details;
        }

        return response()->json(['error' => $error], $status);
    }

    /**
     * @return array{0:int,1:string,2:string,3:array|null}
     */
    private static function map(Throwable $e): array
    {
        return match (true) {
            $e instanceof ValidationException => [
                422, 'validation_error', 'The given data was invalid.', $e->errors(),
            ],
            $e instanceof AuthenticationException => [
                401, 'unauthenticated', 'Authentication required. Provide a valid API key.', null,
            ],
            $e instanceof AuthorizationException => [
                403, 'forbidden', $e->getMessage() ?: 'This action is unauthorized.', null,
            ],
            $e instanceof ModelNotFoundException => [
                404, 'not_found', 'The requested resource was not found.', null,
            ],
            $e instanceof HttpExceptionInterface => [
                $e->getStatusCode(),
                self::codeForStatus($e->getStatusCode()),
                $e->getMessage() ?: self::messageForStatus($e->getStatusCode()),
                null,
            ],
            default => [
                500,
                'server_error',
                config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.',
                null,
            ],
        };
    }

    private static function codeForStatus(int $status): string
    {
        return match ($status) {
            404 => 'not_found',
            405 => 'method_not_allowed',
            429 => 'rate_limited',
            401 => 'unauthenticated',
            403 => 'forbidden',
            default => 'http_error',
        };
    }

    private static function messageForStatus(int $status): string
    {
        return match ($status) {
            404 => 'The requested resource was not found.',
            405 => 'The HTTP method is not allowed for this endpoint.',
            429 => 'Too many requests. Slow down.',
            default => 'Request could not be processed.',
        };
    }
}
