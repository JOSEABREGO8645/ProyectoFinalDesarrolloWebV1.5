<?php

namespace Database\Factories;

use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProveedorFactory extends Factory
{
    protected $model = Proveedor::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->company(),
            'nit' => fake()->unique()->numerify('#########'),
            'telefono' => '2222-1000',
            'correo' => fake()->safeEmail(),
            'direccion' => 'Cuilapa, Santa Rosa',
            'contacto' => fake()->name(),
            'activo' => true,
        ];
    }
}
