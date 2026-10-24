<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Proveedor extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'proveedores';

    protected $table = 'proveedores';

    protected $appends = ['_id'];

    protected $fillable = ['nombre', 'nit', 'telefono', 'correo', 'direccion', 'contacto', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}
