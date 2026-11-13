<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Payment;
use App\Models\User;
use App\Support\CsvExport;
use App\Support\DatabaseBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportAndBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_exportacion_exige_sesion(): void
    {
        $this->getJson('/api/export/clients')->assertUnauthorized();
        $this->getJson('/api/export/payments')->assertUnauthorized();
    }

    public function test_exporta_clientes_en_csv_para_excel(): void
    {
        $this->actingAs(User::factory()->create());
        Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz', 'company_name' => 'Café Ñandú', 'notes' => '=HYPERLINK("http://x")']);

        $response = $this->get('/api/export/clients');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);           // BOM para Excel
        $this->assertStringContainsString('Nombre;Apellido;Empresa', $csv);  // separador ;
        $this->assertStringContainsString('Café Ñandú', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);        // fórmula neutralizada
        $this->assertDatabaseHas('audit_logs', ['event' => 'export.clients']);
    }

    public function test_exporta_pagos_con_importe_en_formato_argentino(): void
    {
        $this->actingAs(User::factory()->create());
        $client = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        Payment::create(['client_id' => $client->id, 'concept' => 'Seña', 'amount' => 1500.5, 'due_date' => today()]);

        $this->assertStringContainsString('1500,50', $this->get('/api/export/payments')->streamedContent());
    }

    public function test_neutraliza_solo_los_textos_peligrosos(): void
    {
        $this->assertSame("'=1+1", CsvExport::cell('=1+1'));
        $this->assertSame("'-cmd", CsvExport::cell('-cmd'));
        $this->assertSame('Hola', CsvExport::cell('Hola'));
        $this->assertSame(-5, CsvExport::cell(-5));
        $this->assertNull(CsvExport::cell(null));
    }

    public function test_la_poda_conserva_las_copias_mas_recientes(): void
    {
        $dir = sys_get_temp_dir().'/lm-backup-test-'.uniqid();
        mkdir($dir);
        foreach (['20260101-000000', '20260102-000000', '20260103-000000', '20260104-000000'] as $stamp) {
            touch("{$dir}/backup-{$stamp}.sql.gz");
        }

        $deleted = (new DatabaseBackup($dir))->prune(2);

        $this->assertSame(2, $deleted);
        $this->assertFileExists("{$dir}/backup-20260104-000000.sql.gz");
        $this->assertFileExists("{$dir}/backup-20260103-000000.sql.gz");
        $this->assertFileDoesNotExist("{$dir}/backup-20260101-000000.sql.gz");

        array_map('unlink', glob("{$dir}/*"));
        rmdir($dir);
    }

    public function test_el_comando_avisa_si_no_puede_respaldar(): void
    {
        // La base de pruebas es SQLite en memoria: no hay archivo que copiar.
        $this->artisan('app:backup')->expectsOutputToContain('no se puede copiar')->assertFailed();
    }
}
