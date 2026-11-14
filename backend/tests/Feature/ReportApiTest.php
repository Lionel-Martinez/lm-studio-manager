<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_exige_sesion_y_un_periodo_valido(): void
    {
        $this->getJson('/api/reports?from=2026-01-01&to=2026-12-31')->assertUnauthorized();

        $this->actingAs(User::factory()->create());
        $this->getJson('/api/reports')->assertUnprocessable()->assertJsonValidationErrors(['from', 'to']);
        $this->getJson('/api/reports?from=2026-12-31&to=2026-01-01')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson('/api/reports?from=2010-01-01&to=2026-01-01')->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_periodo_vacio_devuelve_todos_los_meses_en_cero(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/reports?from=2026-01-15&to=2026-03-10')
            ->assertOk()
            ->assertJsonCount(3, 'data.revenue_by_month')
            ->assertJsonPath('data.revenue_by_month.0', ['month' => '2026-01', 'value' => 0])
            ->assertJsonPath('data.totals.collected', 0)
            ->assertJsonPath('data.totals.avg_wait_days', null);
    }

    public function test_calcula_ingresos_por_mes_servicio_y_cliente(): void
    {
        $this->actingAs(User::factory()->create());

        $ana = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        $leo = Client::create(['first_name' => 'Leo', 'last_name' => 'Paz']);
        $menu = $ana->projects()->create(['service_type' => 'digital_menu', 'status' => 'delivered', 'entry_date' => '2026-02-01']);

        Payment::create(['client_id' => $ana->id, 'project_id' => $menu->id, 'concept' => 'Seña', 'amount' => 3000, 'due_date' => '2026-02-10', 'paid_at' => '2026-02-10']);
        Payment::create(['client_id' => $ana->id, 'project_id' => $menu->id, 'concept' => 'Saldo', 'amount' => 2000, 'due_date' => '2026-03-05', 'paid_at' => '2026-03-05']);
        Payment::create(['client_id' => $leo->id, 'concept' => 'Mantenimiento', 'amount' => 500, 'due_date' => '2026-03-06', 'paid_at' => '2026-03-06']);
        Payment::create(['client_id' => $leo->id, 'concept' => 'Fuera de período', 'amount' => 9999, 'due_date' => '2025-01-01', 'paid_at' => '2025-01-01']);

        $response = $this->getJson('/api/reports?from=2026-01-01&to=2026-03-31')->assertOk();

        $response->assertJsonPath('data.totals.collected', 5500)
            ->assertJsonPath('data.revenue_by_month.1.value', 3000)
            ->assertJsonPath('data.revenue_by_month.2.value', 2500)
            ->assertJsonPath('data.revenue_by_service.0', ['label' => 'Menú digital', 'value' => 5000])
            ->assertJsonPath('data.revenue_by_service.1', ['label' => 'Sin proyecto', 'value' => 500])
            ->assertJsonPath('data.top_clients.0', ['label' => 'Ana Ruiz', 'value' => 5000]);
    }

    public function test_cuenta_proyectos_espera_y_atrasados(): void
    {
        $this->actingAs(User::factory()->create());

        $ana = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        $ana->projects()->create(['service_type' => 'web_app', 'status' => 'in_progress', 'entry_date' => '2026-02-01', 'estimated_start_date' => '2026-02-11', 'due_date' => today()->subDays(3)->toDateString()]);
        $ana->projects()->create(['service_type' => 'web_app', 'status' => 'waiting', 'entry_date' => '2026-02-05', 'estimated_start_date' => '2026-02-25']);
        $ana->projects()->create(['service_type' => 'static_site', 'status' => 'delivered', 'entry_date' => '2026-02-07', 'due_date' => today()->subDays(30)->toDateString()]);

        $response = $this->getJson('/api/reports?from=2026-01-01&to=2026-03-31')->assertOk();

        $response->assertJsonPath('data.totals.projects', 3)
            ->assertJsonPath('data.totals.avg_wait_days', 15)       // (10 + 20) / 2
            ->assertJsonCount(1, 'data.late_projects')               // el entregado no cuenta
            ->assertJsonPath('data.late_projects.0.days_late', 3);

        $byService = collect($response->json('data.projects_by_service'))->pluck('value', 'label');
        $this->assertSame(2, $byService['Aplicación web']);
        $this->assertSame(1, $byService['Página web estática']);
    }

    public function test_clientes_nuevos_por_mes(): void
    {
        $this->actingAs(User::factory()->create());

        Client::create(['first_name' => 'A', 'last_name' => 'A', 'first_contact_at' => '2026-01-20']);
        Client::create(['first_name' => 'B', 'last_name' => 'B', 'first_contact_at' => '2026-01-25']);
        Client::create(['first_name' => 'C', 'last_name' => 'C', 'first_contact_at' => '2026-03-02']);

        $this->getJson('/api/reports?from=2026-01-01&to=2026-03-31')
            ->assertJsonPath('data.new_clients_by_month.0.value', 2)
            ->assertJsonPath('data.new_clients_by_month.1.value', 0)
            ->assertJsonPath('data.new_clients_by_month.2.value', 1);
    }
}
