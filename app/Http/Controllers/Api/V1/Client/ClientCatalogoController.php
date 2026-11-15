<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServicioResource;
use App\Http\Traits\ApiResponse;
use App\Models\Servicio;
use Throwable;

/**
 * Catálogo público de servicios disponibles (no requiere token).
 */
class ClientCatalogoController extends Controller
{
    use ApiResponse;

    // GET /api/v1/client/catalogo
    public function index()
    {
        try {
            $servicios = Servicio::where('estado', true)->get();

            return $this->success(
                'Catálogo consultado correctamente.',
                ServicioResource::collection($servicios)->resolve()
            );
        } catch (Throwable $e) {
            return $this->serverError($e, 'catalogo.index');
        }
    }

    // GET /api/v1/client/catalogo/{id}
    public function show($id)
    {
        if ($invalido = $this->idInvalido($id)) {
            return $invalido;
        }

        try {
            $servicio = Servicio::where('estado', true)->find($id);

            if (!$servicio) {
                return $this->notFound('El servicio solicitado no existe.');
            }

            return $this->success(
                'Servicio consultado correctamente.',
                (new ServicioResource($servicio))->resolve()
            );
        } catch (Throwable $e) {
            return $this->serverError($e, 'catalogo.show');
        }
    }
}
