<?php

/**
 * CORS: solo hace falta cuando el frontend está en otro subdominio que la API
 * (por ejemplo app.tudominio.com.ar → api.tudominio.com.ar). En desarrollo, Vite
 * redirige /api y no hay peticiones entre orígenes.
 *
 * CORS_ALLOWED_ORIGINS: orígenes exactos separados por coma, sin barra final.
 */
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Accept', 'X-XSRF-TOKEN', 'X-Requested-With'],
    'exposed_headers' => [],
    'max_age' => 600,
    // Necesario para que viaje la cookie de sesión.
    'supports_credentials' => true,
];
