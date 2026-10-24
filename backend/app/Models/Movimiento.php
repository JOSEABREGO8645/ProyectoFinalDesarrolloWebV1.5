<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Movimiento extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'movimientos';

    protected $guarded = [];

    protected $appends = ['_id'];

    protected $casts = ['cantidad' => 'integer', 'antes' => 'integer', 'despues' => 'integer'];
}
