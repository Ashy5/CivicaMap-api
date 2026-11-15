<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsuarioResource extends JsonResource
{
    // Solo se exponen campos seguros: nunca password ni campos internos.
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id_usuario,
            'nombre'         => $this->nombre,
            'correo'         => $this->correo,
            'fecha_registro' => $this->fecha_registro?->toIso8601String(),
            'roles'          => $this->whenLoaded('roles', fn () => $this->roles->pluck('nombre')),
        ];
    }
}
