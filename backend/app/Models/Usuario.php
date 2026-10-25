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

    protected $fillable = ['nombre', 'usuario', 'password', 'codigo_hash', 'rol', 'sucursal_id', 'sucursal_ids', 'modulos', 'activo'];

    public function sucursalesAsignadas(): array
    {
        return array_values(array_unique((array) ($this->sucursal_ids ?? [$this->getRawOriginal('sucursal_id') ?? $this->sucursal_id])));
    }

    protected $hidden = ['password', 'codigo_hash', 'remember_token'];

    protected $attributes = ['auth_version' => 0];

    protected $casts = ['activo' => 'boolean', 'auth_version' => 'integer'];
}
