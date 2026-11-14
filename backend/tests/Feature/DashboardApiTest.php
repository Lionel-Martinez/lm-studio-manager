<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Payment;
use App\Models\Region;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_exige_sesion(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_dashboard_vacio(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.kpis.monthly_revenue', 0)
            ->assertJsonPath('data.kpis.active_clients', 0)
            ->assertJsonCount(6, 'data.revenue')
            ->assertJsonPath('data.projects', [])
            ->assertJsonPath('data.distribution', []);
    }

    public function test_calcula_indicadores_con_datos_reales(): void
    {
        $this->actingAs(User::factory()->create());

        $ana = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz'])->refresh();
        $leo = Client::create(['first_name' => 'Leo', 'last_name' => 'Paz'])->refresh();

        $ana->projects()->create(['service_type' => 'web_app', 'status' => 'in_progress', 'entry_date' => today(), 'due_date' => today()->addDays(10)]);
        $ana->projects()->create(['service_type' => 'digital_menu', 'status' => 'ready', 'entry_date' => today()]);
        $leo->projects()->create(['service_type' => 'static_site', 'status' => 'waiting', 'entry_date' => today()]);
        $leo->projects()->create(['service_type' => 'static_site', 'status' => 'delivered', 'entry_date' => today()]);

        Payment::create(['client_id' => $ana->id, 'concept' => 'Seña', 'amount' => 3000, 'due_date' => today(), 'paid_at' => today()]);
        Payment::create(['client_id' => $ana->id, 'concept' => 'Cuota', 'amount' => 2000, 'due_date' => today()->subDays(2)]);
        Payment::create(['client_id' => $leo->id, 'concept' => 'Cuota', 'amount' => 500, 'due_date' => today()->addDays(4)]);

        $response = $this->getJson('/api/dashboard')->assertOk();

        $response->assertJsonPath('data.kpis.monthly_revenue', 3000)
            ->assertJsonPath('data.kpis.active_clients', 1)
            ->assertJsonPath('data.kpis.prospects', 1)
            ->assertJsonPath('data.kpis.pending_payments', 1)
            ->assertJsonPath('data.kpis.overdue_count', 1)
            ->assertJsonPath('data.kpis.overdue_amount', 2000)
            ->assertJsonCount(3, 'data.projects')   // el entregado no aparece
            ->assertJsonCount(2, 'data.payments')   // solo los no cobrados
            ->assertJsonPath('data.revenue.5.value', 3000)
            ->assertJsonPath('data.distribution.0.label', 'Sin ubicación');
    }

    public function test_agrupa_clientes_por_departamento(): void
    {
        $this->actingAs(User::factory()->create());

        $country = Country::create(['name' => 'Argentina', 'iso2' => 'AR']);
        $region = Region::create(['country_id' => $country->id, 'name' => 'Salta']);
        $oran = Region::create(['country_id' => $country->id, 'parent_id' => $region->id, 'name' => 'Orán', 'region_type' => 'department']);

        Client::create(['first_name' => 'A', 'last_name' => 'A', 'region_id' => $oran->id]);
        Client::create(['first_name' => 'B', 'last_name' => 'B', 'region_id' => $oran->id]);

        $this->getJson('/api/dashboard')->assertJsonPath('data.distribution.0', ['label' => 'Orán', 'value' => 2]);
    }

    public function test_los_cobros_de_un_cliente_eliminado_siguen_contando(): void
    {
        $this->actingAs(User::factory()->create());

        $ana = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz'])->refresh();
        Payment::create(['client_id' => $ana->id, 'concept' => 'Cuota', 'amount' => 2000, 'due_date' => today()->subDays(2)]);
        $ana->delete();

        $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('data.kpis.overdue_count', 1)
            ->assertJsonPath('data.kpis.overdue_amount', 2000)
            ->assertJsonCount(1, 'data.payments')
            ->assertJsonPath('data.payments.0.client', 'Ana Ruiz');
    }
}
