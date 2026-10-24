<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Sucursal extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'sucursales';

    protected $appends = ['_id'];

    protected $fillable = [
        'nombre',
        'direccion',
        'telefono',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
