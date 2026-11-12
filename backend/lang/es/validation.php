<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'email' => 'Ingresá un correo válido.',
    'max' => ['string' => 'El campo :attribute no puede superar los :max caracteres.'],
    'min' => ['string' => 'El campo :attribute debe tener al menos :min caracteres.'],
    'unique' => 'Ese :attribute ya está registrado.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'different' => 'El campo :attribute debe ser distinto de :other.',
    'current_password' => 'La contraseña actual no es correcta.',
    'date' => 'El campo :attribute no es una fecha válida.',
    'exists' => 'El valor elegido para :attribute no existe.',
    'enum' => 'El valor elegido para :attribute no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'after_or_equal' => 'El campo :attribute debe ser una fecha igual o posterior a :date.',
    'array' => 'El campo :attribute no es válido.',
    'required_with' => 'El campo :attribute es obligatorio.',
    'password' => [
        'letters' => 'La :attribute debe incluir al menos una letra.',
        'mixed' => 'La :attribute debe incluir mayúsculas y minúsculas.',
        'numbers' => 'La :attribute debe incluir al menos un número.',
        'symbols' => 'La :attribute debe incluir al menos un símbolo.',
        'uncompromised' => 'Esa :attribute apareció en filtraciones de datos. Elegí otra.',
    ],
    'attributes' => [
        'email' => 'correo',
        'password' => 'contraseña',
        'current_password' => 'contraseña actual',
        'name' => 'nombre',
    ],
];
