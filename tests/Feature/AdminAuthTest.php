<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioConRol(string $correo, string $rol): Usuario
    {
        $usuario = Usuario::create([
            'nombre' => "Usuario {$rol}", 'correo' => $correo, 'password' => 'password123',
            'fecha_registro' => now(), 'estado' => true,
        ]);
        $usuario->roles()->attach(Rol::firstOrCreate(['nombre' => $rol])->id_rol);

        return $usuario;
    }

    private function loginAdmin(): string
    {
        $this->usuarioConRol('admin@test.com', 'Administrador');

        return $this->postJson('/api/v1/admin/login', [
            'correo' => 'admin@test.com', 'password' => 'password123',
        ])->assertOk()->json('data.token');
    }

    // Cada petición con un token distinto debe reiniciar el guard (cache del usuario en pruebas)
    private function como(string $token): static
    {
        app('auth')->forgetGuards();

        return $this->withToken($token);
    }

    public function test_rutas_admin_sin_token_devuelven_401(): void
    {
        foreach (['reportes', 'categorias', 'evidencias', 'perfil'] as $recurso) {
            $this->getJson("/api/v1/admin/{$recurso}")
                ->assertStatus(401)
                ->assertJsonPath('success', false);
        }
        $this->postJson('/api/v1/admin/logout')->assertStatus(401);
    }

    public function test_login_admin_devuelve_token_y_perfil_con_rol(): void
    {
        $this->usuarioConRol('admin@test.com', 'Administrador');

        $this->postJson('/api/v1/admin/login', ['correo' => 'admin@test.com', 'password' => 'password123'])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.usuario.correo', 'admin@test.com')
            ->assertJsonPath('data.usuario.roles.0', 'Administrador')
            ->assertJsonMissingPath('data.usuario.password');
    }

    public function test_login_con_credenciales_incorrectas_devuelve_401(): void
    {
        $this->usuarioConRol('admin@test.com', 'Administrador');

        $this->postJson('/api/v1/admin/login', ['correo' => 'admin@test.com', 'password' => 'mala'])
            ->assertStatus(401);
    }

    public function test_cliente_no_puede_iniciar_sesion_como_admin(): void
    {
        $this->usuarioConRol('cliente@test.com', 'Cliente');

        $this->postJson('/api/v1/admin/login', ['correo' => 'cliente@test.com', 'password' => 'password123'])
            ->assertStatus(403);
    }

    public function test_token_valido_permite_consultar_rutas_admin(): void
    {
        $token = $this->loginAdmin();

        $this->como($token)->getJson('/api/v1/admin/reportes')->assertOk();
        $this->como($token)->getJson('/api/v1/admin/categorias')->assertOk();
        $this->como($token)->getJson('/api/v1/admin/perfil')
            ->assertOk()->assertJsonPath('data.correo', 'admin@test.com');
    }

    public function test_token_de_cliente_en_rutas_admin_devuelve_403(): void
    {
        $this->usuarioConRol('cliente@test.com', 'Cliente');
        $token = $this->postJson('/api/v1/client/login', [
            'correo' => 'cliente@test.com', 'password' => 'password123',
        ])->assertOk()->json('data.token');

        $this->como($token)->getJson('/api/v1/admin/reportes')
            ->assertStatus(403)->assertJsonPath('success', false);
    }

    public function test_token_alterado_devuelve_401(): void
    {
        $this->como('999|token-inventado')->getJson('/api/v1/admin/reportes')->assertStatus(401);
    }

    public function test_logout_revoca_el_token_en_la_base_de_datos(): void
    {
        $token = $this->loginAdmin();
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->como($token)->postJson('/api/v1/admin/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->como($token)->getJson('/api/v1/admin/reportes')->assertStatus(401);
    }

    public function test_rutas_publicas_de_lectura_siguen_disponibles_pero_sin_escritura_publica(): void
    {
        $this->getJson('/api/v1/reportes')->assertOk();
        $this->getJson('/api/v1/categorias')->assertOk();

        $this->postJson('/api/v1/reportes', [])->assertStatus(401);
        $this->postJson('/api/v1/evidencias', [])->assertStatus(401);
        $this->deleteJson('/api/v1/reportes/1')->assertStatus(405);
        $this->patchJson('/api/v1/categorias/1', [])->assertStatus(405);
    }
}
