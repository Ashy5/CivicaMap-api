<?php

namespace Tests\Feature;

use App\Models\Servicio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientApiTest extends TestCase
{
    use RefreshDatabase;

    private function registrarYLogin(string $correo): string
    {
        $this->postJson('/api/v1/client/register', [
            'nombre'   => 'Cliente ' . $correo,
            'correo'   => $correo,
            'password' => 'password123',
        ])->assertCreated();

        return $this->postJson('/api/v1/client/login', [
            'correo'   => $correo,
            'password' => 'password123',
        ])->assertOk()->json('data.token');
    }

    // Cada petición con un token distinto debe reiniciar el guard (cache del usuario en pruebas)
    private function como(string $token): static
    {
        app('auth')->forgetGuards();

        return $this->withToken($token);
    }

    public function test_cliente_solo_accede_a_sus_propios_registros(): void
    {
        $servicio = Servicio::create([
            'nombre' => 'Bacheo', 'descripcion' => null, 'tipo' => 'Vialidad',
            'costo' => 350, 'estado' => true,
        ]);

        $tokenA = $this->registrarYLogin('a@test.com');
        $tokenB = $this->registrarYLogin('b@test.com');

        // A crea un registro (intenta inyectar id_usuario: debe ignorarse)
        $id = $this->como($tokenA)->postJson('/api/v1/client/mis-registros', [
            'id_usuario'    => 999,
            'observaciones' => 'Registro de A',
            'servicios'     => [['id_servicio' => $servicio->id_servicio, 'cantidad' => 2]],
        ])->assertCreated()
          ->assertJsonStructure(['success', 'message', 'data', 'errors'])
          ->json('data.id');

        // A ve su registro; B ve una lista vacía
        $this->como($tokenA)->getJson('/api/v1/client/mis-registros')
            ->assertOk()->assertJsonCount(1, 'data');
        $this->como($tokenB)->getJson('/api/v1/client/mis-registros')
            ->assertOk()->assertJsonCount(0, 'data');

        // B intenta ver, modificar y cancelar el registro de A: 403
        $this->como($tokenB)->getJson("/api/v1/client/mis-registros/$id")
            ->assertForbidden()->assertJson(['success' => false, 'data' => null]);
        $this->como($tokenB)->putJson("/api/v1/client/mis-registros/$id", ['observaciones' => 'hack'])
            ->assertForbidden();
        $this->como($tokenB)->deleteJson("/api/v1/client/mis-registros/$id")
            ->assertForbidden();

        // A modifica y cancela su propio registro
        $this->como($tokenA)->patchJson("/api/v1/client/mis-registros/$id", ['observaciones' => 'Editado'])
            ->assertOk();
        $this->como($tokenA)->deleteJson("/api/v1/client/mis-registros/$id")
            ->assertOk()->assertJsonPath('data.estado', 'cancelada');

        // Un registro cancelado ya no se puede cancelar de nuevo (403 por estado)
        $this->como($tokenA)->deleteJson("/api/v1/client/mis-registros/$id")->assertForbidden();

        // Registro inexistente: 404
        $this->como($tokenA)->getJson('/api/v1/client/mis-registros/99999')->assertNotFound();
    }

    public function test_sin_token_devuelve_401_json(): void
    {
        $this->getJson('/api/v1/client/mis-registros')
            ->assertUnauthorized()
            ->assertJsonStructure(['success', 'message', 'data', 'errors']);
    }

    public function test_logout_revoca_el_token(): void
    {
        $token = $this->registrarYLogin('c@test.com');
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->como($token)->postJson('/api/v1/client/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_registro_invalido_devuelve_422(): void
    {
        $this->postJson('/api/v1/client/register', ['correo' => 'no-es-correo'])
            ->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'data', 'errors' => ['nombre', 'correo', 'password']]);
    }
}
