<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Note;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Receipt;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedDemoDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_datos_de_prueba_coherentes(): void
    {
        $this->artisan('app:seed-demo')->assertSuccessful();

        $this->assertSame(8, Client::where('source', 'demo')->count());
        $this->assertGreaterThanOrEqual(8, Project::count());
        $this->assertGreaterThan(10, Payment::count());
        $this->assertSame(1, Receipt::count());
        $this->assertSame(3, Note::count());
        $this->assertSame(3, Reminder::count());
        $this->assertSame(1, Client::where('commercial_status', 'inactive')->where('deactivation_reason', 'non_payment')->count());

        // Hay de todo para probar: cobrados, vencidos y por vencer.
        $this->assertGreaterThan(0, Payment::withStatus('paid')->count());
        $this->assertGreaterThan(0, Payment::withStatus('overdue')->count());
        $this->assertGreaterThan(0, Payment::withStatus('pending')->count());

        // Las pantallas con datos no fallan.
        $this->actingAs(User::factory()->create());
        $from = today()->subYear()->toDateString();
        $to = today()->addMonths(3)->toDateString();
        foreach (['dashboard', "reports?from={$from}&to={$to}", 'agenda', 'services', 'geography', 'payments', 'clients'] as $path) {
            $this->getJson("/api/{$path}")->assertOk();
        }
    }

    public function test_correrlo_dos_veces_no_duplica(): void
    {
        $this->artisan('app:seed-demo')->assertSuccessful();
        $this->artisan('app:seed-demo')->assertSuccessful();

        $this->assertSame(8, Client::where('source', 'demo')->count());
        $this->assertSame(1, Receipt::count());
    }

    public function test_remove_borra_solo_lo_de_prueba(): void
    {
        $real = Client::create(['first_name' => 'Real', 'last_name' => 'Cliente']);
        $realPayment = Payment::create(['client_id' => $real->id, 'concept' => 'Seña', 'amount' => 100, 'due_date' => today()]);
        Note::create(['title' => 'Mi nota', 'body' => 'x']);

        $this->artisan('app:seed-demo')->assertSuccessful();
        $this->artisan('app:seed-demo', ['--remove' => true])->assertSuccessful();

        $this->assertSame(1, Client::withTrashed()->count());
        $this->assertSame(1, Payment::withTrashed()->count());
        $this->assertDatabaseHas('payments', ['id' => $realPayment->id]);
        $this->assertSame(1, Note::count());
        $this->assertSame(0, Receipt::count());
        $this->assertSame(0, Reminder::count());
    }

    public function test_en_produccion_pide_confirmacion(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('app:seed-demo')->assertFailed();
        $this->assertSame(0, Client::count());

        $this->artisan('app:seed-demo', ['--force' => true])->assertSuccessful();
        $this->assertSame(8, Client::count());
    }
}
