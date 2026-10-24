<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Existencia extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'existencias';

    protected $table = 'existencias';

    protected $appends = ['_id'];

    protected $fillable = ['producto_id', 'sucursal_id', 'cantidad'];

    protected $casts = ['cantidad' => 'integer'];
}
