<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['ventas', 'ingresos', 'traslados'] as $coleccion) {
            Schema::connection('mongodb')->table($coleccion, function (MongoDB\Laravel\Schema\Blueprint $table) {
                $table->index('created_at');
                $table->index(['sucursal_id', 'created_at']);
            });
        }
        Schema::connection('mongodb')->table('traslados', fn (MongoDB\Laravel\Schema\Blueprint $table) => $table->index('estado'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['ventas', 'ingresos', 'traslados'] as $coleccion) {
            Schema::connection('mongodb')->table($coleccion, function (MongoDB\Laravel\Schema\Blueprint $table) {
                $table->dropIndex(['created_at']);
                $table->dropIndex(['sucursal_id', 'created_at']);
            });
        }
        Schema::connection('mongodb')->table('traslados', fn (MongoDB\Laravel\Schema\Blueprint $table) => $table->dropIndex(['estado']));
    }
};
