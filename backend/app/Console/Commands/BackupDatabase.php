<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Support\DatabaseBackup;
use Illuminate\Console\Command;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'app:backup {--keep= : Cuántas copias conservar (por defecto BACKUP_KEEP o 14)}';

    protected $description = 'Guarda una copia comprimida de la base de datos en storage/app/private/backups';

    public function handle(): int
    {
        $keep = (int) ($this->option('keep') ?: env('BACKUP_KEEP', 14));

        $backup = new DatabaseBackup(
            storage_path('app/private/backups'),
            (string) env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
        );

        try {
            $file = $backup->run();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $deleted = $backup->prune($keep);

        // Anotar la actividad es secundario: si falla (p. ej. falta migrar), la copia ya está hecha.
        try {
            AuditLog::record('backup.created', null, ['file' => basename($file), 'pruned' => $deleted]);
        } catch (Throwable $e) {
            $this->warn('La copia se hizo, pero no se pudo anotar en la actividad: '.$e->getMessage());
        }

        $this->info('Copia creada: '.$file);
        if ($deleted > 0) {
            $this->line("Se borraron {$deleted} copia(s) antigua(s); se conservan las últimas {$keep}.");
        }

        return self::SUCCESS;
    }
}
