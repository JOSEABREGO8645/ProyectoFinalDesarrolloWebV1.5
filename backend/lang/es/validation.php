<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'email' => 'El campo :attribute debe ser un correo electrónico válido.',
    'numeric' => 'El campo :attribute debe ser un número.',
    'decimal' => 'El campo :attribute debe tener entre :min y :max decimales.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'array' => 'El campo :attribute debe ser una lista.',
    'date' => 'El campo :attribute debe ser una fecha válida.',
    'date_format' => 'El campo :attribute no tiene el formato :format.',
    'after_or_equal' => 'El campo :attribute debe ser una fecha igual o posterior a :date.',
    'digits' => 'El campo :attribute debe tener :digits dígitos.',
    'in' => 'El valor elegido en :attribute no es válido.',
    'regex' => 'El formato del campo :attribute no es válido.',
    'min' => [
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
    ],
    'max' => [
        'numeric' => 'El campo :attribute no puede ser mayor que :max.',
        'string' => 'El campo :attribute no puede tener más de :max caracteres.',
        'array' => 'El campo :attribute no puede tener más de :max elementos.',
    ],

    'attributes' => [
        'nombre' => 'nombre', 'direccion' => 'dirección', 'telefono' => 'teléfono',
        'correo' => 'correo', 'nit' => 'NIT', 'contacto' => 'persona de contacto',
        'activo' => 'estado', 'codigo' => 'código', 'descripcion' => 'descripción',
        'categoria' => 'categoría', 'proveedor_id' => 'proveedor',
        'precio_costo' => 'precio de costo', 'precio_venta' => 'precio de venta',
        'stock_minimo' => 'stock mínimo', 'usuario' => 'usuario', 'password' => 'contraseña',
        'rol' => 'rol', 'sucursal_id' => 'sucursal', 'modulos' => 'módulos',
        'cliente_id' => 'cliente', 'efectivo' => 'efectivo recibido',
        'items' => 'detalle', 'items.*.producto_id' => 'producto', 'items.*.cantidad' => 'cantidad',
        'destino_id' => 'sucursal destino', 'motivo' => 'motivo', 'observacion' => 'observación',
        'producto_id' => 'producto', 'cantidad' => 'cantidad', 'costo_unitario' => 'costo unitario',
        'moneda' => 'moneda', 'documento' => 'documento', 'refresh_token' => 'token de renovación',
    ],
];
