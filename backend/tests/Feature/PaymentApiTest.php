<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function client(): Client
    {
        return Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz'])->refresh();
    }

    private function payment(Client $client, array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'client_id' => $client->id,
            'concept' => 'Seña',
            'amount' => 50000,
            'due_date' => today()->addDays(5)->toDateString(),
        ], $overrides));
    }

    public function test_registra_un_pago_pendiente(): void
    {
        $client = $this->client();

        $this->postJson('/api/payments', [
            'client_id' => $client->id,
            'concept' => 'Seña menú digital',
            'amount' => 75000.5,
            'due_date' => today()->addWeek()->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.amount', 75000.5)
            ->assertJsonPath('data.client_name', 'Ana Ruiz');
    }

    public function test_calcula_pendiente_vencido_y_cobrado(): void
    {
        $client = $this->client();
        $this->payment($client);
        $this->payment($client, ['due_date' => today()->subDays(3)->toDateString()]);
        $this->payment($client, ['paid_at' => today()->toDateString()]);

        $this->getJson('/api/payments?status=pending')->assertJsonCount(1, 'data');
        $this->getJson('/api/payments?status=overdue')->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'overdue');
        $this->getJson('/api/payments?status=paid')->assertJsonCount(1, 'data');
        $this->getJson('/api/payments')->assertJsonCount(3, 'data');
    }

    public function test_marca_un_pago_como_cobrado(): void
    {
        $payment = $this->payment($this->client());

        $this->putJson("/api/payments/{$payment->id}", [
            'concept' => 'Seña',
            'amount' => 50000,
            'due_date' => $payment->due_date->toDateString(),
            'paid_at' => today()->toDateString(),
            'method' => 'Transferencia',
        ])->assertOk()->assertJsonPath('data.status', 'paid');
    }

    public function test_valida_importe_y_proyecto_de_otro_cliente(): void
    {
        $client = $this->client();
        $other = Client::create(['first_name' => 'Otro', 'last_name' => 'Cliente']);
        $project = $other->projects()->create(['service_type' => 'web_app', 'status' => 'waiting', 'entry_date' => today()]);

        $this->postJson('/api/payments', [
            'client_id' => $client->id,
            'project_id' => $project->id,
            'concept' => 'X',
            'amount' => 0,
            'due_date' => today()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['amount', 'project_id']);
    }

    public function test_resumen_de_totales(): void
    {
        $client = $this->client();
        $this->payment($client, ['amount' => 1000]);
        $this->payment($client, ['amount' => 2000, 'due_date' => today()->subDay()->toDateString()]);
        $this->payment($client, ['amount' => 4000, 'paid_at' => today()->toDateString()]);

        $this->getJson('/api/payments/summary')
            ->assertOk()
            ->assertJsonPath('data.pending', 1000)
            ->assertJsonPath('data.overdue', 2000)
            ->assertJsonPath('data.overdue_count', 1)
            ->assertJsonPath('data.collected_this_month', 4000);
    }

    public function test_elimina_con_baja_logica_y_exige_sesion(): void
    {
        $payment = $this->payment($this->client());

        $this->deleteJson("/api/payments/{$payment->id}")->assertNoContent();
        $this->assertSoftDeleted('payments', ['id' => $payment->id]);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/payments')->assertUnauthorized();
    }
}
