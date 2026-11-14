<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class PruneAuditLogs extends Command
{
    protected $signature = 'app:prune-audit-logs {--months= : Meses a conservar (por defecto AUDIT_LOG_RETENTION_MONTHS o 12)}';

    protected $description = 'Borra del registro de actividad lo anterior al período de retención';

    public function handle(): int
    {
        $months = (int) ($this->option('months') ?: config('business.audit_retention_months', 12));

        if ($months < 1) {
            $this->error('El período de retención debe ser de al menos 1 mes.');

            return self::FAILURE;
        }

        $deleted = AuditLog::where('created_at', '<', now()->subMonths($months))->delete();

        $this->info("Registros de actividad borrados: {$deleted} (se conservan los últimos {$months} meses).");

        return self::SUCCESS;
    }
}
