<?php

use App\Http\Controllers\Api\V1\CategoriaReporteController;
use App\Http\Controllers\Api\V1\Client\ClientAuthController;
use App\Http\Controllers\Api\V1\Client\ClientCatalogoController;
use App\Http\Controllers\Api\V1\Client\ClientPerfilController;
use App\Http\Controllers\Api\V1\Client\ClientSolicitudController;
use App\Http\Controllers\Api\V1\EvidenciaController;
use App\Http\Controllers\Api\V1\ReporteController;
use Illuminate\Support\Facades\Route;

// Laravel agrega automáticamente el prefijo /api, por lo que las rutas quedan en /api/v1/...
Route::prefix('v1')->group(function () {

    // Prueba de funcionamiento
    Route::get('/prueba', function () {
        return response()->json([
            'success' => true,
            'message' => 'La API de CivicaMap está funcionando.',
            'data'    => null,
            'errors'  => null,
        ]);
    });

    // TEMPORAL para pruebas de integración: las consultas GET no requieren Bearer.
    // Estos apiResource no tienen auth:sanctum; sus operaciones de escritura también quedan públicas.
    // Reinstalar la protección antes de exponer la API fuera del entorno de pruebas.
    Route::apiResource('categorias', CategoriaReporteController::class)->parameters(['categorias' => 'id']);
    Route::apiResource('reportes', ReporteController::class)->parameters(['reportes' => 'id']);
    Route::apiResource('evidencias', EvidenciaController::class)->parameters(['evidencias' => 'id']);

    // Reemplazar la imagen de una evidencia (multipart/form-data)
    Route::post('/evidencias/{id}/imagen', [EvidenciaController::class, 'reemplazarImagen']);

    // ------------------------------------------------------------------
    // Área del cliente: /api/v1/client/...
    // ------------------------------------------------------------------
    Route::prefix('client')->group(function () {

        // Públicas
        Route::post('register', [ClientAuthController::class, 'register']);
        Route::post('login', [ClientAuthController::class, 'login'])->middleware('throttle:10,1');
        Route::get('catalogo', [ClientCatalogoController::class, 'index']);
        Route::get('catalogo/{id}', [ClientCatalogoController::class, 'show']);

        // Privadas: requieren Bearer token de Sanctum
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [ClientAuthController::class, 'logout']);

            Route::get('perfil', [ClientPerfilController::class, 'show']);
            Route::match(['put', 'patch'], 'perfil', [ClientPerfilController::class, 'update']);

            Route::get('mis-registros', [ClientSolicitudController::class, 'index']);
            Route::post('mis-registros', [ClientSolicitudController::class, 'store']);
            Route::get('mis-registros/{id}', [ClientSolicitudController::class, 'show']);
            Route::match(['put', 'patch'], 'mis-registros/{id}', [ClientSolicitudController::class, 'update']);
            Route::delete('mis-registros/{id}', [ClientSolicitudController::class, 'destroy']);
        });
    });
});
