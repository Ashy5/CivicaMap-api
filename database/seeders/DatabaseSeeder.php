<?php

namespace Database\Seeders;

use App\Models\CategoriaReporte;
use App\Models\Reporte;
use App\Models\Rol;
use App\Models\Servicio;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Datos de ejemplo para probar la API desde Postman.
     * Administrador de prueba: admin@civicamap.test (contraseña: password123)
     * Clientes de prueba (contraseña: password123):
     *   clienteA@civicamap.test  y  clienteB@civicamap.test
     */
    public function run(): void
    {
        $rolCliente = Rol::firstOrCreate(
            ['nombre' => 'Cliente'],
            ['descripcion' => 'Usuario final del sistema']
        );
        $rolAdmin = Rol::firstOrCreate(
            ['nombre' => 'Administrador'],
            ['descripcion' => 'Administrador del sistema']
        );

        // Administrador de prueba para el Área Administrativa (Actividad 5.2)
        $admin = Usuario::firstOrCreate(
            ['correo' => 'admin@civicamap.test'],
            [
                'nombre'         => 'Administrador CivicaMap',
                'password'       => 'password123', // el cast "hashed" lo cifra
                'fecha_registro' => now(),
                'estado'         => true,
            ]
        );
        $admin->roles()->syncWithoutDetaching([$rolAdmin->id_rol]);

        $clientes = [];
        foreach (['A', 'B'] as $letra) {
            $cliente = Usuario::firstOrCreate(
                ['correo' => "cliente{$letra}@civicamap.test"],
                [
                    'nombre'         => "Cliente {$letra}",
                    'password'       => 'password123', // el cast "hashed" lo cifra
                    'fecha_registro' => now(),
                    'estado'         => true,
                ]
            );
            $cliente->roles()->syncWithoutDetaching([$rolCliente->id_rol]);
            $clientes[$letra] = $cliente;
        }

        // Catálogo de servicios
        $servicios = [
            ['Reparación de bache', 'Bacheo de vialidades', 'Vialidad', 350.00],
            ['Poda de árbol', 'Poda en vía pública', 'Parques y jardines', 200.00],
            ['Reparación de luminaria', 'Cambio de lámpara de alumbrado', 'Alumbrado', 150.00],
        ];
        foreach ($servicios as [$nombre, $descripcion, $tipo, $costo]) {
            Servicio::firstOrCreate(
                ['nombre' => $nombre],
                ['descripcion' => $descripcion, 'tipo' => $tipo, 'costo' => $costo, 'estado' => true]
            );
        }

        // Categorías y un reporte de ejemplo (API administrativa de la actividad 4.2)
        $categorias = [];
        foreach (['Baches', 'Alumbrado público', 'Fugas de agua'] as $nombre) {
            $categorias[] = CategoriaReporte::firstOrCreate(
                ['nombre' => $nombre],
                ['descripcion' => "Reportes de {$nombre}", 'estado' => true]
            );
        }

        if (Reporte::count() === 0) {
            Reporte::create([
                'id_usuario'     => $clientes['A']->id_usuario,
                'id_categoria'   => $categorias[0]->id_categoria,
                'descripcion'    => 'Bache grande en la esquina de la avenida principal.',
                'latitud_aprox'  => 20.6596988,
                'longitud_aprox' => -103.3496092,
                'fecha_reporte'  => now(),
                'estado'         => 'pendiente',
                'anonimizado'    => false,
            ]);
        }
    }
}
