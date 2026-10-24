<?php

namespace Database\Factories;

use App\Models\Sucursal;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'usuario' => fake()->unique()->bothify('empleado_########'),
            'password' => Hash::make('PruebaSegura2026!'),
            'rol' => 'empleado',
            'sucursal_id' => fn () => (string) Sucursal::create(['nombre' => 'Sucursal de pruebas', 'activo' => true])->getKey(),
            'modulos' => [],
            'activo' => true,
        ];
    }
}
