<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClientLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ruta pública
    }

    public function rules(): array
    {
        return [
            'correo'   => 'required|email|max:150',
            'password' => 'required|string',
        ];
    }
}
