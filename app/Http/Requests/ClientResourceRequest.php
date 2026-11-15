<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida las solicitudes ("mis-registros") del cliente.
 * Nunca se acepta id_usuario: el propietario se asigna en el servidor con auth()->id().
 */
class ClientResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        // Crear (POST)
        if ($this->isMethod('post')) {
            return [
                'observaciones'            => 'nullable|string|max:500',
                'servicios'                => 'required|array|min:1|max:20',
                'servicios.*.id_servicio'  => [
                    'required', 'integer', 'distinct',
                    Rule::exists('servicios', 'id_servicio')->where('estado', 1),
                ],
                'servicios.*.cantidad'     => 'required|integer|min:1|max:100',
            ];
        }

        // Actualizar (PUT/PATCH): solo las observaciones son editables
        $req = $this->isMethod('put') ? 'required' : 'sometimes|required';

        return [
            'observaciones' => "$req|string|max:500",
        ];
    }
}
