<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ruta pública: aquí se obtiene el token
    }

    public function rules(): array
    {
        return [
            'correo'   => 'required|email|max:150',
            'password' => 'required|string',
        ];
    }
}
