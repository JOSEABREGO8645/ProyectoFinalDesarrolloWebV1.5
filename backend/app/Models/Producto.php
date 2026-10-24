<?php

namespace App\Models;

use Database\Factories\ProductoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Producto extends Model
{
    /** @use HasFactory<ProductoFactory> */
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'productos';

    protected $table = 'productos';

    protected $appends = ['_id'];

    protected $fillable = ['codigo', 'nombre', 'descripcion', 'categoria', 'proveedor_id', 'precio_costo', 'precio_venta', 'stock_minimo', 'activo'];

    protected $casts = ['precio_costo' => 'decimal:2', 'precio_venta' => 'decimal:2', 'stock_minimo' => 'integer', 'activo' => 'boolean'];
}
