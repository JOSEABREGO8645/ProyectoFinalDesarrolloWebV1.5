<?php

namespace Database\Factories;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->bothify('PROD-#####'),
            'nombre' => 'Producto ficticio',
            'categoria' => 'Papelería',
            'proveedor_id' => null,
            'precio_costo' => '5.50',
            'precio_venta' => '10.00',
            'stock_minimo' => 5,
            'activo' => true,
        ];
    }
}
