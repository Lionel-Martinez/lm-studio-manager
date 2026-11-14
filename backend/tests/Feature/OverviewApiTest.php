<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Country;
use App\Models\Payment;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_exigen_sesion(): void
    {
        $this->getJson('/api/services')->assertUnauthorized();
        $this->getJson('/api/geography')->assertUnauthorized();
    }

    public function test_servicios_resume_proyectos_clientes_y_dinero(): void
    {
        $this->actingAs(User::factory()->create());

        $ana = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        $leo = Client::create(['first_name' => 'Leo', 'last_name' => 'Paz']);
        $menu = $ana->projects()->create(['service_type' => 'digital_menu', 'status' => 'delivered', 'entry_date' => '2026-01-01']);
        $ana->projects()->create(['service_type' => 'digital_menu', 'status' => 'in_progress', 'entry_date' => '2026-02-01', 'due_date' => today()->subDay()->toDateString()]);
        $leo->projects()->create(['service_type' => 'digital_menu', 'status' => 'waiting', 'entry_date' => '2026-02-01']);

        Payment::create(['client_id' => $ana->id, 'project_id' => $menu->id, 'concept' => 'Seña', 'amount' => 3000, 'due_date' => '2026-01-10', 'paid_at' => '2026-01-10']);
        Payment::create(['client_id' => $ana->id, 'project_id' => $menu->id, 'concept' => 'Saldo', 'amount' => 700, 'due_date' => '2026-03-10']);

        $response = $this->getJson('/api/services')->assertOk()->assertJsonCount(4, 'data');

        $menuRow = collect($response->json('data'))->firstWhere('type', 'digital_menu');
        $this->assertSame(3, $menuRow['projects']);
        $this->assertSame(2, $menuRow['clients']);
        $this->assertSame(1, $menuRow['late']);
        $this->assertEquals(3000, $menuRow['collected']);
        $this->assertEquals(700, $menuRow['pending']);
        $this->assertSame(1, collect($menuRow['by_status'])->firstWhere('status', 'delivered')['count']);

        $web = collect($response->json('data'))->firstWhere('type', 'web_app');
        $this->assertSame(0, $web['projects']);
    }

    public function test_geografia_agrupa_por_pais_provincia_y_departamento(): void
    {
        $this->actingAs(User::factory()->create());

        $ar = Country::create(['name' => 'Argentina', 'iso2' => 'AR']);
        $salta = Region::create(['country_id' => $ar->id, 'name' => 'Salta', 'region_type' => 'province']);
        $oran = Region::create(['country_id' => $ar->id, 'parent_id' => $salta->id, 'name' => 'Orán', 'region_type' => 'department']);

        $ana = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz', 'country_id' => $ar->id, 'region_id' => $oran->id]);
        Client::create(['first_name' => 'Leo', 'last_name' => 'Paz', 'country_id' => $ar->id, 'region_id' => $salta->id]);
        $sin = Client::create(['first_name' => 'Sol', 'last_name' => 'Gil']);

        Payment::create(['client_id' => $ana->id, 'concept' => 'Seña', 'amount' => 1000, 'due_date' => '2026-01-10', 'paid_at' => '2026-01-10']);
        Payment::create(['client_id' => $sin->id, 'concept' => 'Seña', 'amount' => 400, 'due_date' => '2026-01-10', 'paid_at' => '2026-01-10']);

        $data = $this->getJson('/api/geography')->assertOk()->json('data');

        $this->assertSame(3, $data['total_clients']);
        $this->assertSame(2, $data['countries'][0]['clients']);
        $this->assertEquals(1000, $data['countries'][0]['revenue']);
        $this->assertCount(1, $data['provinces']);
        $this->assertSame(2, $data['provinces'][0]['clients']);
        $this->assertSame($ar->id, $data['provinces'][0]['country_id']);
        $this->assertCount(1, $data['departments']);
        $this->assertSame('Orán', $data['departments'][0]['name']);
        $this->assertSame($salta->id, $data['departments'][0]['province_id']);
        $this->assertSame(1, $data['unlocated']['clients']);
        $this->assertEquals(400, $data['unlocated']['revenue']);
    }
}
