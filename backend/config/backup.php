<?php

/** Copias de seguridad. Va en config (no con env() suelto) para que funcione con `config:cache`. */
return [
    'keep' => (int) env('BACKUP_KEEP', 14),
    'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
];
