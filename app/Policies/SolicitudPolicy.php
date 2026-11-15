<?php

namespace App\Policies;

use App\Models\Solicitud;
use App\Models\Usuario;
use Illuminate\Auth\Access\Response;

/**
 * Autorización de datos propios (Self-Data Ownership).
 * Compara la identidad del usuario autenticado con el propietario del registro.
 */
class SolicitudPolicy
{
    private function esPropietario(Usuario $user, Solicitud $solicitud): bool
    {
        // $user->id_usuario viene del token de Sanctum; $solicitud->id_usuario es el dueño.
        return (int) $user->id_usuario === (int) $solicitud->id_usuario;
    }

    /** Consultar el detalle: solo el propietario. */
    public function view(Usuario $user, Solicitud $solicitud): Response
    {
        return $this->esPropietario($user, $solicitud)
            ? Response::allow()
            : Response::deny('No tienes permiso para acceder a este registro.');
    }

    /** Actualizar: solo el propietario. */
    public function update(Usuario $user, Solicitud $solicitud): Response
    {
        return $this->esPropietario($user, $solicitud)
            ? Response::allow()
            : Response::deny('No tienes permiso para modificar este registro.');
    }

    /** Cancelar: solo el propietario y solo si el registro está en un estado cancelable. */
    public function delete(Usuario $user, Solicitud $solicitud): Response
    {
        if (!$this->esPropietario($user, $solicitud)) {
            return Response::deny('No tienes permiso para cancelar este registro.');
        }

        return $solicitud->estado === Solicitud::ESTADO_PENDIENTE
            ? Response::allow()
            : Response::deny("El registro no se puede cancelar porque su estado es '{$solicitud->estado}'.");
    }
}
