<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->company(),
            'nit' => fake()->unique()->numerify('#########'),
            'telefono' => '2222-1000',
            'correo' => fake()->safeEmail(),
            'direccion' => 'Cuilapa, Santa Rosa',

        ];
    }
}
