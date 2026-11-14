<?php

/** Datos que se imprimen en el encabezado de los comprobantes (se configuran en .env). */
return [
    'name' => env('BUSINESS_NAME', 'LM Studio'),
    'tax_id' => env('BUSINESS_TAX_ID'),
    'address' => env('BUSINESS_ADDRESS'),
    'contact' => env('BUSINESS_CONTACT'),
    // Cuántos meses se conserva el registro de actividad antes de borrarse solo.
    'audit_retention_months' => (int) env('AUDIT_LOG_RETENTION_MONTHS', 12),
];
