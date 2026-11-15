<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SolicitudResource extends JsonResource
{
    // No se expone id_usuario: el cliente solo ve sus propios registros.
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id_solicitud,
            'estado'          => $this->estado,
            'fecha_solicitud' => $this->fecha_solicitud?->toIso8601String(),
            'observaciones'   => $this->observaciones,
            'detalles'        => SolicitudDetalleResource::collection($this->whenLoaded('detalles')),
            'total'           => $this->whenLoaded('detalles', fn () => round(
                $this->detalles->sum(fn ($d) => $d->cantidad * (float) $d->precio),
                2
            )),
        ];
    }
}
