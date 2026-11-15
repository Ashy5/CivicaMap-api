<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientLoginRequest;
use App\Http\Requests\ClientRegisterRequest;
use App\Http\Resources\UsuarioResource;
use App\Http\Traits\ApiResponse;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class ClientAuthController extends Controller
{
    use ApiResponse;

    // POST /api/v1/client/register
    public function register(ClientRegisterRequest $request)
    {
        try {
            $data = $request->validated();

            $usuario = DB::transaction(function () use ($data) {
                $usuario = Usuario::create([
                    'nombre'         => $data['nombre'],
                    'correo'         => $data['correo'],
                    'password'       => $data['password'], // el cast "hashed" lo cifra
                    'fecha_registro' => now(),
                    'estado'         => true,
                ]);

                // El rol Cliente se asigna automáticamente (el cliente no puede elegirlo)
                $rol = Rol::firstOrCreate(
                    ['nombre' => 'Cliente'],
                    ['descripcion' => 'Usuario final del sistema']
                );
                $usuario->roles()->attach($rol->id_rol);

                return $usuario;
            });

            return $this->success(
                'Cliente registrado correctamente.',
                (new UsuarioResource($usuario->load('roles')))->resolve(),
                201
            );
        } catch (Throwable $e) {
            return $this->serverError($e, 'client.register');
        }
    }

    // POST /api/v1/client/login
    public function login(ClientLoginRequest $request)
    {
        try {
            $usuario = Usuario::where('correo', $request->input('correo'))->first();

            if (!$usuario || !Hash::check($request->input('password'), $usuario->password ?? '')) {
                return $this->error('Las credenciales son incorrectas.', 401);
            }

            if (!$usuario->estado) {
                return $this->error('La cuenta está desactivada.', 403);
            }

            if (!$usuario->esCliente()) {
                return $this->error('Esta cuenta no tiene acceso al área de clientes.', 403);
            }

            $token = $usuario->createToken('client-token', ['client'])->plainTextToken;

            return $this->success('Inicio de sesión correcto.', [
                'token'      => $token,
                'token_type' => 'Bearer',
                'usuario'    => (new UsuarioResource($usuario->load('roles')))->resolve(),
            ]);
        } catch (Throwable $e) {
            return $this->serverError($e, 'client.login');
        }
    }

    // POST /api/v1/client/logout  (auth:sanctum)
    public function logout(Request $request)
    {
        try {
            // Revoca únicamente el token con el que se hizo la petición
            $request->user()->currentAccessToken()->delete();

            return $this->success('Sesión cerrada correctamente.');
        } catch (Throwable $e) {
            return $this->serverError($e, 'client.logout');
        }
    }
}
