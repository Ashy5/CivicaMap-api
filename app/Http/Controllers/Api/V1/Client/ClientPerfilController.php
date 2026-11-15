<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientProfileRequest;
use App\Http\Resources\UsuarioResource;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Perfil del cliente autenticado. No recibe ningún id: siempre es el dueño del token.
 */
class ClientPerfilController extends Controller
{
    use ApiResponse;

    // GET /api/v1/client/perfil
    public function show(Request $request)
    {
        return $this->success(
            'Perfil consultado correctamente.',
            (new UsuarioResource($request->user()->load('roles')))->resolve()
        );
    }

    // PUT / PATCH /api/v1/client/perfil
    public function update(ClientProfileRequest $request)
    {
        try {
            $usuario = $request->user();

            // Solo nombre, correo y password: el cliente no puede tocar estado ni roles
            $usuario->update($request->validated());

            return $this->success(
                'Perfil actualizado correctamente.',
                (new UsuarioResource($usuario->fresh('roles')))->resolve()
            );
        } catch (Throwable $e) {
            return $this->serverError($e, 'perfil.update');
        }
    }
}
