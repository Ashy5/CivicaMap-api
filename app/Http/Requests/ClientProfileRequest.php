<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        // PUT reemplaza (obligatorios); PATCH es parcial.
        $req = $this->isMethod('put') ? 'required' : 'sometimes|required';

        return [
            'nombre'   => "$req|string|max:100",
            'correo'   => [
                ...explode('|', "$req|email|max:150"),
                Rule::unique('usuarios', 'correo')->ignore($this->user()->id_usuario, 'id_usuario'),
            ],
            'password' => 'sometimes|required|string|min:8|max:100',
        ];
    }
}
