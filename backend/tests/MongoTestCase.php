<?php

namespace Tests;

use Illuminate\Support\Facades\DB;

abstract class MongoTestCase extends TestCase
{
    private string $baseTemporal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseTemporal = 'libreria_catalogos_test_'.bin2hex(random_bytes(8));
        config(['database.connections.mongodb.database' => $this->baseTemporal]);
        config(['jwt.secret' => str_repeat('clave-exclusiva-de-pruebas-', 3), 'jwt.cookie_secure' => false]);
        DB::purge('mongodb');
        $this->app->setLocale('es');
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->baseTemporal) && preg_match('/^libreria_catalogos_test_[a-f0-9]{16}$/', $this->baseTemporal)) {
                DB::connection('mongodb')->getMongoDB()->drop();
                DB::purge('mongodb');
            }
        } finally {
            parent::tearDown();
        }
    }
}
