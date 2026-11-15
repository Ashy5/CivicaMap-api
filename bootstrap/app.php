<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

// Proyecto solo API: no existen rutas web ni vistas. El front vive en otro proyecto.
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // Toda respuesta de error de este proyecto es JSON (nunca HTML ni redirecciones).
        $exceptions->shouldRenderJsonWhen(fn (Request $request, \Throwable $e): bool => true);

        $exceptions->render(function (\Throwable $e, Request $request) {

            $respuesta = fn (string $mensaje, int $status, $errores = null) => response()->json([
                'success' => false,
                'message' => $mensaje,
                'data'    => null,
                'errors'  => $errores,
            ], $status);

            // Errores de validación
            if ($e instanceof ValidationException) {
                return $respuesta('Los datos enviados no son válidos.', 422, $e->errors());
            }

            // Registro inexistente (route model binding / findOrFail)
            if ($e instanceof ModelNotFoundException) {
                return $respuesta('El recurso solicitado no existe.', 404);
            }

            // Acceso denegado por Policy/Gate (propiedad de datos): 403
            if ($e instanceof AuthorizationException) {
                $mensaje = $e->getMessage();

                if ($mensaje === '' || $mensaje === 'This action is unauthorized.') {
                    $mensaje = 'No tienes permiso para realizar esta acción.';
                }

                return $respuesta($mensaje, 403);
            }

            // Sin token, token inválido o expirado: 401
            if ($e instanceof AuthenticationException) {
                return $respuesta('No autenticado.', 401);
            }

            // Errores HTTP: 400, 403, 404, 405, 413, 415, 429...
            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();

                // 403: conserva el mensaje de la Policy/Gate (Laravel convierte la
                // AuthorizationException en un HttpException 403 antes de llegar aquí)
                if ($status === 403) {
                    $mensaje = $e->getMessage();

                    if ($mensaje === '' || $mensaje === 'This action is unauthorized.') {
                        $mensaje = 'No tienes permiso para realizar esta acción.';
                    }

                    return $respuesta($mensaje, 403);
                }

                $mensajes = [
                    400 => 'Solicitud incorrecta.',
                    401 => 'No autenticado.',
                    403 => 'Acceso prohibido.',
                    404 => 'El recurso solicitado no existe.',
                    405 => 'Método HTTP no permitido para este endpoint.',
                    413 => 'El archivo o la solicitud es demasiado grande.',
                    415 => 'Tipo de contenido no admitido.',
                    429 => 'Demasiadas solicitudes. Intente más tarde.',
                ];

                return $respuesta($mensajes[$status] ?? 'Error en la solicitud.', $status);
            }

            // Cualquier otro error: 500 sin detalles internos.
            // Laravel ya registra la excepción en storage/logs antes de llegar aquí.
            return $respuesta('Ocurrió un error interno al procesar la solicitud.', 500);
        });

    })->create();
