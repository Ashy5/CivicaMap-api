<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Respuestas JSON estandarizadas: success, message, data, errors.
 */
trait ApiResponse
{
    protected function success(string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'errors'  => null,
        ], $status);
    }

    protected function error(string $message, int $status, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => null,
            'errors'  => $errors,
        ], $status);
    }

    protected function validationError($validator): JsonResponse
    {
        return $this->error('Los datos enviados no son válidos.', 422, $validator->errors());
    }

    protected function notFound(string $message): JsonResponse
    {
        return $this->error($message, 404);
    }

    /**
     * Registra el error técnico en el log y responde 500 sin exponer detalles.
     */
    protected function serverError(Throwable $e, string $context = ''): JsonResponse
    {
        Log::error('Error en la API: ' . $context . ' | ' . $e->getMessage(), [
            'exception' => $e,
        ]);

        return $this->error('Ocurrió un error interno al procesar la solicitud.', 500);
    }

    /**
     * Un identificador válido es un entero positivo. Si no, la API responde 400.
     */
    protected function idInvalido(mixed $id): ?JsonResponse
    {
        if (!ctype_digit((string) $id) || (int) $id < 1) {
            return $this->error('El identificador debe ser un número entero positivo.', 400);
        }

        return null;
    }

    /**
     * PUT reemplaza (campos obligatorios); PATCH actualiza parcialmente (sometimes).
     */
    protected function reglaPresencia(\Illuminate\Http\Request $request): string
    {
        return $request->isMethod('put') ? 'required' : 'sometimes|required';
    }
}
