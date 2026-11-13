<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Copia de seguridad de la base de datos en un archivo .sql.gz dentro de storage (fuera de public).
 * MySQL/MariaDB usa mysqldump; SQLite (solo para pruebas locales) copia el archivo.
 */
class DatabaseBackup
{
    public function __construct(
        private readonly string $directory,
        private readonly string $mysqldump = 'mysqldump',
    ) {}

    /** Crea la copia y devuelve la ruta del archivo. */
    public function run(): string
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0750, true) && ! is_dir($this->directory)) {
            throw new RuntimeException("No se pudo crear la carpeta {$this->directory}.");
        }

        $config = config('database.connections.'.config('database.default'));
        $stamp = now()->format('Ymd-His');

        return match ($config['driver']) {
            'mysql', 'mariadb' => $this->dumpMysql($config, "{$this->directory}/backup-{$stamp}.sql.gz"),
            'sqlite' => $this->copySqlite($config, "{$this->directory}/backup-{$stamp}.sqlite"),
            default => throw new RuntimeException("Driver no soportado para copias: {$config['driver']}."),
        };
    }

    /** Conserva solo las `$keep` copias más recientes. Devuelve cuántas borró. */
    public function prune(int $keep): int
    {
        $files = glob("{$this->directory}/backup-*") ?: [];
        rsort($files); // El nombre lleva la fecha, así que ordenar por nombre = por antigüedad.

        $old = array_slice($files, max($keep, 1));
        foreach ($old as $file) {
            @unlink($file);
        }

        return count($old);
    }

    private function dumpMysql(array $config, string $target): string
    {
        $process = new Process([
            $this->mysqldump,
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--user='.$config['username'],
            '--single-transaction',
            '--no-tablespaces',
            $config['database'],
        ], null, ['MYSQL_PWD' => (string) ($config['password'] ?? '')]); // La clave no viaja en la línea de comandos.

        $process->setTimeout(300);

        $gz = gzopen($target, 'wb9');
        if ($gz === false) {
            throw new RuntimeException("No se pudo escribir {$target}.");
        }

        try {
            $process->run(function (string $type, string $buffer) use ($gz) {
                if ($type === Process::OUT) {
                    gzwrite($gz, $buffer);
                }
            });
        } finally {
            gzclose($gz);
        }

        if (! $process->isSuccessful()) {
            @unlink($target);
            throw new RuntimeException('mysqldump falló: '.trim($process->getErrorOutput()));
        }

        chmod($target, 0640);

        return $target;
    }

    private function copySqlite(array $config, string $target): string
    {
        $source = $config['database'];

        if ($source === ':memory:' || ! is_file($source)) {
            throw new RuntimeException('La base SQLite no es un archivo: no se puede copiar.');
        }

        DB::disconnect();
        copy($source, $target);
        chmod($target, 0640);

        return $target;
    }
}
