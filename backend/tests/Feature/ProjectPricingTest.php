<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectPricingTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());

        $client = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        $this->project = $client->projects()->create(['service_type' => 'web_app', 'name' => 'Tienda', 'status' => 'in_progress', 'entry_date' => '2026-01-01', 'due_date' => '2026-03-31']);
    }

    private function url(string $suffix = ''): string
    {
        return "/api/projects/{$this->project->id}/pricing{$suffix}";
    }

    public function test_pago_unico(): void
    {
        $this->postJson($this->url('/preview'), ['total_price' => 100000, 'payment_mode' => 'single', 'plan_start_date' => '2026-02-01'])
            ->assertOk()
            ->assertJsonCount(1, 'data.schedule')
            ->assertJsonPath('data.schedule.0.amount', 100000)
            ->assertJsonPath('data.schedule.0.due_date', '2026-02-01');
    }

    public function test_anticipo_y_saldo_con_porcentaje_configurable(): void
    {
        $r = $this->postJson($this->url('/preview'), ['total_price' => 100000, 'payment_mode' => 'split', 'deposit_percent' => 30, 'plan_start_date' => '2026-02-01'])->assertOk();

        $r->assertJsonPath('data.schedule.0.concept', 'Anticipo 30%')
            ->assertJsonPath('data.schedule.0.amount', 30000)
            ->assertJsonPath('data.schedule.1.concept', 'Saldo 70%')
            ->assertJsonPath('data.schedule.1.amount', 70000)
            // El saldo vence en la fecha de entrega del proyecto.
            ->assertJsonPath('data.schedule.1.due_date', '2026-03-31');
    }

    public function test_cuotas_suman_exactamente_el_total(): void
    {
        $r = $this->postJson($this->url('/preview'), ['total_price' => 100, 'payment_mode' => 'installments', 'installments_count' => 3, 'plan_start_date' => '2026-01-31'])->assertOk();

        $r->assertJsonCount(3, 'data.schedule')
            ->assertJsonPath('data.schedule.0.amount', 33.33)
            ->assertJsonPath('data.schedule.2.amount', 33.34)
            ->assertJsonPath('data.schedule_total', 100)
            // Enero 31 + 1 mes cae a fin de febrero, sin saltar a marzo.
            ->assertJsonPath('data.schedule.1.due_date', '2026-02-28');
    }

    public function test_cuota_mensual_espera_prueba_y_meses_gratis(): void
    {
        $r = $this->postJson($this->url('/preview'), [
            'monthly_fee' => 5000, 'monthly_months' => 3, 'free_months' => 2,
            'trial_value' => 2, 'trial_unit' => 'weeks', 'plan_start_date' => '2026-02-01',
        ])->assertOk();

        // 1 de febrero + 2 semanas = 15 de febrero; + 2 meses gratis = 15 de abril.
        $r->assertJsonCount(3, 'data.schedule')
            ->assertJsonPath('data.schedule.0.due_date', '2026-04-15')
            ->assertJsonPath('data.schedule.1.due_date', '2026-05-15')
            ->assertJsonPath('data.schedule.0.concept', 'Cuota mensual 1/3');
    }

    public function test_la_vista_previa_no_guarda_nada(): void
    {
        $this->postJson($this->url('/preview'), ['total_price' => 100, 'payment_mode' => 'single', 'plan_start_date' => '2026-02-01'])->assertOk();

        $this->assertNull($this->project->fresh()->total_price);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_guardar_y_generar_crea_pagos_pendientes(): void
    {
        $this->putJson($this->url(), ['total_price' => 90000, 'payment_mode' => 'installments', 'installments_count' => 3, 'plan_start_date' => '2026-02-01', 'generate' => true])
            ->assertOk()->assertJsonPath('data.generated', 3)->assertJsonPath('data.project.total_price', 90000);

        $this->assertSame(3, Payment::where('project_id', $this->project->id)->whereNull('paid_at')->count());
        $this->assertDatabaseHas('payments', ['plan_item' => 'installment-1', 'amount' => 30000]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'project.pricing']);
    }

    public function test_regenerar_reemplaza_los_pagos_pendientes_sin_duplicar(): void
    {
        $data = ['total_price' => 90000, 'payment_mode' => 'installments', 'installments_count' => 3, 'plan_start_date' => '2026-02-01', 'generate' => true];
        $this->putJson($this->url(), $data)->assertOk();
        $this->putJson($this->url(), ['installments_count' => 2] + $data)->assertOk();

        $this->assertSame(2, Payment::where('project_id', $this->project->id)->count());
    }

    public function test_no_rehace_el_calendario_si_ya_hay_cobros(): void
    {
        $data = ['total_price' => 90000, 'payment_mode' => 'installments', 'installments_count' => 3, 'plan_start_date' => '2026-02-01', 'generate' => true];
        $this->putJson($this->url(), $data)->assertOk();
        Payment::where('plan_item', 'installment-1')->update(['paid_at' => '2026-02-01']);

        $this->putJson($this->url(), ['installments_count' => 2] + $data)->assertUnprocessable()->assertJsonValidationErrors('generate');

        $this->assertSame(3, Payment::where('project_id', $this->project->id)->count());
    }

    public function test_no_toca_pagos_cargados_a_mano(): void
    {
        Payment::create(['client_id' => $this->project->client_id, 'project_id' => $this->project->id, 'concept' => 'Manual', 'amount' => 10, 'due_date' => '2026-02-01']);

        $this->putJson($this->url(), ['total_price' => 100, 'payment_mode' => 'single', 'plan_start_date' => '2026-02-01', 'generate' => true])->assertOk();

        $this->assertSame(2, Payment::where('project_id', $this->project->id)->count());
    }

    public function test_valida_los_datos_segun_la_forma_de_pago(): void
    {
        $this->putJson($this->url(), ['total_price' => 100, 'plan_start_date' => '2026-02-01'])->assertUnprocessable()->assertJsonValidationErrors('payment_mode');
        $this->putJson($this->url(), ['total_price' => 100, 'payment_mode' => 'split', 'plan_start_date' => '2026-02-01'])->assertUnprocessable()->assertJsonValidationErrors('deposit_percent');
        $this->putJson($this->url(), ['total_price' => 100, 'payment_mode' => 'installments', 'installments_count' => 1, 'plan_start_date' => '2026-02-01'])->assertUnprocessable()->assertJsonValidationErrors('installments_count');
        $this->putJson($this->url(), ['monthly_fee' => 10, 'plan_start_date' => '2026-02-01'])->assertUnprocessable()->assertJsonValidationErrors('monthly_months');
        $this->putJson($this->url(), ['trial_value' => 3, 'plan_start_date' => '2026-02-01'])->assertUnprocessable()->assertJsonValidationErrors('trial_unit');
        $this->putJson($this->url(), ['total_price' => 100, 'payment_mode' => 'single'])->assertUnprocessable()->assertJsonValidationErrors('plan_start_date');
        $this->putJson($this->url(), ['plan_start_date' => '2026-02-01', 'generate' => true])->assertUnprocessable()->assertJsonValidationErrors('total_price');
    }
}
