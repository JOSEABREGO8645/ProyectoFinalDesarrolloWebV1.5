<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\MongoTestCase;

class CachePublicacionTest extends MongoTestCase
{
    public function test_cache_y_limites_persisten_al_recrear_el_adaptador(): void
    {
        config(['cache.default' => 'mongodb', 'cache.prefix' => 'publicacion-test:']);
        $this->app->forgetInstance(\Illuminate\Cache\RateLimiter::class);
        RateLimiter::clearResolvedInstance(\Illuminate\Cache\RateLimiter::class);
        Cache::store()->put('tasa-ficticia', ['referencia' => '7.650000'], 60);
        RateLimiter::hit('login-ficticio', 60);
        $primero = Cache::store()->lock('documento-ficticio', 30);
        $this->assertTrue($primero->get());

        Cache::forgetDriver('mongodb');
        $this->app->forgetInstance('cache.store');
        $this->app->forgetInstance(\Illuminate\Cache\RateLimiter::class);
        RateLimiter::clearResolvedInstance(\Illuminate\Cache\RateLimiter::class);

        $this->assertSame(['referencia' => '7.650000'], Cache::store()->get('tasa-ficticia'));
        $this->assertSame(1, RateLimiter::attempts('login-ficticio'));
        $segundo = Cache::store()->lock('documento-ficticio', 30);
        $this->assertFalse($segundo->get());
        $this->assertTrue($primero->release());
        $this->assertTrue($segundo->get());
        $segundo->release();
    }
}
