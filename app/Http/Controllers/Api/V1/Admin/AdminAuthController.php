<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use App\Http\Resources\UsuarioResource;
use App\Http\Traits\ApiResponse;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * Autenticación del Área Administrativa (Sanctum, token Bearer).
 * El token emitido solo lleva la habilidad "admin"; los tokens de cliente
 * ("client") no pueden usar las rutas /admin (reciben 403).
 */
class AdminAuthController extends Controller
{
    use ApiResponse;

    // POST /api/v1/admin/login
    public function login(AdminLoginRequest $request)
    {
        try {
            $usuario = Usuario::where('correo', $request->input('correo'))->first();

            if (!$usuario || !Hash::check($request->input('password'), $usuario->password ?? '')) {
                return $this->error('Las credenciales son incorrectas.', 401);
            }

            if (!$usuario->estado) {
                return $this->error('La cuenta está desactivada.', 403);
            }

            if (!$usuario->esAdministrador()) {
                return $this->error('Esta cuenta no tiene acceso al área administrativa.', 403);
            }

            $token = $usuario->createToken('admin-token', ['admin'])->plainTextToken;

            return $this->success('Inicio de sesión correcto.', [
                'token'      => $token,
                'token_type' => 'Bearer',
                'usuario'    => (new UsuarioResource($usuario->load('roles')))->resolve(),
            ]);
        } catch (Throwable $e) {
            return $this->serverError($e, 'admin.login');
        }
    }

    // GET /api/v1/admin/perfil  (auth:sanctum + abilities:admin)
    public function perfil(Request $request)
    {
        return $this->success(
            'Perfil consultado correctamente.',
            (new UsuarioResource($request->user()->load('roles')))->resolve()
        );
    }

    // POST /api/v1/admin/logout  (auth:sanctum + abilities:admin)
    public function logout(Request $request)
    {
        try {
            // Revoca únicamente el token con el que se hizo la petición
            $request->user()->currentAccessToken()->delete();

            return $this->success('Sesión cerrada correctamente.');
        } catch (Throwable $e) {
            return $this->serverError($e, 'admin.logout');
        }
    }
}
