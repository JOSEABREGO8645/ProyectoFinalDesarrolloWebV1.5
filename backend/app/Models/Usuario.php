<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Auth\User as Authenticatable;

class Usuario extends Authenticatable
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $table = 'usuarios';

    protected $appends = ['_id'];

    protected $fillable = ['nombre', 'usuario', 'password', 'codigo_hash', 'rol', 'sucursal_id', 'modulos', 'activo'];

    protected $hidden = ['password', 'codigo_hash', 'remember_token'];

    protected $attributes = ['auth_version' => 0];

    protected $casts = ['activo' => 'boolean', 'auth_version' => 'integer'];
}
