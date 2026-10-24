<?php

namespace Tests;

use App\Models\Usuario;

abstract class CatalogoTestCase extends MongoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $administrador = Usuario::factory()->create(['rol' => 'superusuario']);
        $login = $this->withHeader('X-Requested-With', 'XMLHttpRequest')->postJson('/api/auth/login', [
            'usuario' => $administrador->usuario, 'password' => 'PruebaSegura2026!',
        ])->assertOk();
        $this->withToken($login->json('access_token'));
    }
}
