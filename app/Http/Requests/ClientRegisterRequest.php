<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClientRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ruta pública
    }

    public function rules(): array
    {
        return [
            'nombre'   => 'required|string|max:100',
            'correo'   => 'required|email|max:150|unique:usuarios,correo',
            'password' => 'required|string|min:8|max:100',
        ];
    }
}
