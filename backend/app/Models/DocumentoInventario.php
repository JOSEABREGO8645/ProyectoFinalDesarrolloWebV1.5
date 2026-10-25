<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

abstract class DocumentoInventario extends Model
{
    protected $connection = 'mongodb';

    protected $guarded = [];

    protected $appends = ['_id'];

    protected $hidden = ['huella', 'api_en_curso'];

    protected $casts = ['numero' => 'integer', 'total_centavos' => 'integer', 'efectivo_centavos' => 'integer', 'cambio_centavos' => 'integer'];
}
