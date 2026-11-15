<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiFormatoTest extends TestCase
{
    private function estructura(): array
    {
        return ['success', 'message', 'data', 'errors'];
    }

    public function test_prueba_devuelve_json_estandar(): void
    {
        $this->getJson('/api/v1/prueba')
            ->assertOk()
            ->assertJsonStructure($this->estructura())
            ->assertJson(['success' => true]);
    }

    public function test_ruta_inexistente_devuelve_json_404(): void
    {
        $this->getJson('/api/v1/no-existe')
            ->assertNotFound()
            ->assertJsonStructure($this->estructura())
            ->assertJson(['success' => false, 'data' => null]);
    }

    public function test_metodo_no_permitido_devuelve_json_405(): void
    {
        $this->patchJson('/api/v1/prueba')
            ->assertStatus(405)
            ->assertJsonStructure($this->estructura())
            ->assertJson(['success' => false]);
    }
}
