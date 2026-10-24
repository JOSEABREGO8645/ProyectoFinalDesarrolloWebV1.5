<?php

namespace Tests;

use App\Models\Usuario;
use Firebase\JWT\JWT;
use Illuminate\Testing\TestResponse;

abstract class AuthTestCase extends MongoTestCase
{
    protected function login(Usuario $usuario, string $password = 'PruebaSegura2026!'): TestResponse
    {
        return $this->postJson('/api/auth/login', ['usuario' => $usuario->usuario, 'password' => $password], ['X-Requested-With' => 'XMLHttpRequest']);
    }

    protected function refresh(string $cookie): TestResponse
    {
        return $this->withCredentials()->withUnencryptedCookie(config('jwt.cookie'), $cookie)
            ->postJson('/api/auth/refresh', [], ['X-Requested-With' => 'XMLHttpRequest']);
    }

    protected function cookie(TestResponse $respuesta): string
    {
        return $respuesta->getCookie(config('jwt.cookie'), false)->getValue();
    }

    protected function tearDown(): void
    {
        JWT::$timestamp = null;
        parent::tearDown();
    }
}
