<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Sesion extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'auth_sessions';

    protected $fillable = ['usuario_id', 'auth_version', 'refresh_hash', 'previous_refresh_hash', 'refresh_expires_at', 'revoked_at', 'rotated_at'];

    protected $hidden = ['refresh_hash', 'previous_refresh_hash'];

    protected $casts = ['auth_version' => 'integer', 'refresh_expires_at' => 'integer', 'revoked_at' => 'integer', 'rotated_at' => 'integer'];
}
