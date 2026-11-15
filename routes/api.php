<?php

use App\Http\Controllers\Api\V1\Admin\AdminAuthController;
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

    // ------------------------------------------------------------------
    // Lectura pública para el mapa ciudadano (solo GET; nunca escritura)
    // ------------------------------------------------------------------
    Route::apiResource('categorias', CategoriaReporteController::class)
        ->parameters(['categorias' => 'id'])
        ->only(['index', 'show']);
    Route::apiResource('reportes', ReporteController::class)
        ->parameters(['reportes' => 'id'])
        ->only(['index', 'show']);

    // Un usuario autenticado (cliente) puede registrar un reporte y su evidencia
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('reportes', [ReporteController::class, 'store']);
        Route::post('evidencias', [EvidenciaController::class, 'store']);
    });

    // ------------------------------------------------------------------
    // Área administrativa: /api/v1/admin/...
    // Protección auth:sanctum REACTIVADA (Actividad 5.2): sin un token Bearer
    // válido la API responde 401; con un token sin la habilidad "admin" responde 403.
    // ------------------------------------------------------------------
    Route::prefix('admin')->group(function () {

        // Pública: aquí se obtiene el token
        Route::post('login', [AdminAuthController::class, 'login'])->middleware('throttle:10,1');

        // Privadas: requieren Bearer token de Sanctum con habilidad "admin"
        Route::middleware(['auth:sanctum', 'abilities:admin'])->group(function () {
            Route::post('logout', [AdminAuthController::class, 'logout']);
            Route::get('perfil', [AdminAuthController::class, 'perfil']);

            Route::apiResource('categorias', CategoriaReporteController::class)->parameters(['categorias' => 'id']);
            Route::apiResource('reportes', ReporteController::class)->parameters(['reportes' => 'id']);
            Route::apiResource('evidencias', EvidenciaController::class)->parameters(['evidencias' => 'id']);

            // Reemplazar la imagen de una evidencia (multipart/form-data)
            Route::post('evidencias/{id}/imagen', [EvidenciaController::class, 'reemplazarImagen']);
        });
    });

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
