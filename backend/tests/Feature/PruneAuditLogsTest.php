<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneAuditLogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_borra_solo_lo_anterior_al_periodo_de_retencion(): void
    {
        $old = AuditLog::record('auth.login');
        $old->forceFill(['created_at' => now()->subMonths(13)])->save();
        $recent = AuditLog::record('auth.login');
        $recent->forceFill(['created_at' => now()->subMonths(11)])->save();

        $this->artisan('app:prune-audit-logs')->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $recent->id]);
    }

    public function test_permite_otro_plazo(): void
    {
        $log = AuditLog::record('auth.login');
        $log->forceFill(['created_at' => now()->subMonths(4)])->save();

        $this->artisan('app:prune-audit-logs', ['--months' => 3])->assertSuccessful();
        $this->assertDatabaseMissing('audit_logs', ['id' => $log->id]);

    }
}
