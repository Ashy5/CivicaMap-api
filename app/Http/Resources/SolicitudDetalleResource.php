<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SolicitudDetalleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_servicio' => $this->id_servicio,
            'servicio'    => $this->whenLoaded('servicio', fn () => $this->servicio->nombre),
            'cantidad'    => $this->cantidad,
            'precio'      => (float) $this->precio,
            'subtotal'    => round($this->cantidad * (float) $this->precio, 2),
        ];
    }
}
