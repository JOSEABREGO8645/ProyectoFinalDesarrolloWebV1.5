<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function (Request $request) {
    $connection = DB::connection('mongodb');

    try {
        $connection->command(['ping' => 1]);
        return response()->json([
            'ok' => true,
            'mensaje' => 'MongoDB conectado correctamente'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'ok' => false,
            'mensaje' => 'No fue posible conectar con MongoDB',
            'error' => $e->getMessage()
        ], 500);
    }
});