<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('mongodb')->table('existencias', function (Blueprint $table) {
            $table->unique(['producto_id', 'sucursal_id']);
        });
        foreach (['ingresos', 'ventas', 'traslados'] as $coleccion) {
            Schema::connection('mongodb')->table($coleccion, function (Blueprint $table) {
                $table->unique('operacion_id');
                $table->unique(['sucursal_id', 'numero']);
            });
        }
        Schema::connection('mongodb')->table('movimientos', function (Blueprint $table) {
            $table->index(['sucursal_id', 'created_at']);
            $table->index('documento_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mongodb')->table('existencias', fn (Blueprint $table) => $table->dropUnique(['producto_id', 'sucursal_id']));
        foreach (['ingresos', 'ventas', 'traslados'] as $coleccion) {
            Schema::connection('mongodb')->table($coleccion, function (Blueprint $table) {
                $table->dropUnique(['operacion_id']);
                $table->dropUnique(['sucursal_id', 'numero']);
            });
        }
        Schema::connection('mongodb')->table('movimientos', function (Blueprint $table) {
            $table->dropIndex(['sucursal_id', 'created_at']);
            $table->dropIndex(['documento_id']);
        });
    }
};
