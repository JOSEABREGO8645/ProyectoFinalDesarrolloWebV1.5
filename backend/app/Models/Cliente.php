<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'clientes';

    protected $table = 'clientes';

    protected $appends = ['_id'];

    protected $fillable = ['nombre', 'nit', 'telefono', 'correo', 'direccion'];
}
