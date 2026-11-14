<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Payment;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_exige_sesion(): void
    {
        $this->getJson('/api/agenda')->assertUnauthorized();
        $this->postJson('/api/reminders', [])->assertUnauthorized();
    }

    public function test_junta_entregas_inicios_cobros_y_recordatorios_separando_atrasados(): void
    {
        $this->actingAs(User::factory()->create());

        $ana = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        $ana->projects()->create(['service_type' => 'web_app', 'status' => 'in_progress', 'entry_date' => '2026-01-01', 'due_date' => today()->subDays(2)->toDateString()]);
        $ana->projects()->create(['service_type' => 'static_site', 'status' => 'waiting', 'entry_date' => '2026-01-01', 'estimated_start_date' => today()->addDays(3)->toDateString()]);
        // Entregado: no debe aparecer. Lejano: tampoco.
        $ana->projects()->create(['service_type' => 'web_app', 'status' => 'delivered', 'entry_date' => '2026-01-01', 'due_date' => today()->subDays(5)->toDateString()]);
        $ana->projects()->create(['service_type' => 'web_app', 'status' => 'in_progress', 'entry_date' => '2026-01-01', 'due_date' => today()->addDays(200)->toDateString()]);

        Payment::create(['client_id' => $ana->id, 'concept' => 'Saldo', 'amount' => 1000, 'due_date' => today()->addDays(5)->toDateString()]);
        Payment::create(['client_id' => $ana->id, 'concept' => 'Ya cobrado', 'amount' => 1, 'due_date' => today()->toDateString(), 'paid_at' => today()->toDateString()]);

        Reminder::create(['title' => 'Llamar a Ana', 'remind_on' => today()->toDateString()]);
        Reminder::create(['title' => 'Hecho', 'remind_on' => today()->toDateString(), 'done_at' => now()]);

        $response = $this->getJson('/api/agenda?days=30')->assertOk();

        $response->assertJsonCount(1, 'data.overdue')
            ->assertJsonPath('data.overdue.0.type', 'delivery')
            ->assertJsonCount(3, 'data.upcoming')
            ->assertJsonPath('data.upcoming.0.type', 'reminder')
            ->assertJsonPath('data.upcoming.1.type', 'start')
            ->assertJsonPath('data.upcoming.2.type', 'payment')
            ->assertJsonPath('data.upcoming.2.amount', 1000);

        $this->getJson('/api/agenda/summary')->assertOk()
            ->assertJsonPath('data.overdue', 1)
            ->assertJsonPath('data.today', 1);
    }

    public function test_crear_editar_completar_y_borrar_recordatorio(): void
    {
        $this->actingAs(User::factory()->create());

        $id = $this->postJson('/api/reminders', ['title' => 'Mandar presupuesto', 'remind_on' => today()->addDay()->toDateString()])
            ->assertCreated()->assertJsonPath('data.done', false)->json('data.id');

        $this->putJson("/api/reminders/{$id}", ['title' => 'Mandar presupuesto', 'remind_on' => today()->addDay()->toDateString(), 'done' => true])
            ->assertOk()->assertJsonPath('data.done', true);

        $this->getJson('/api/agenda')->assertJsonCount(0, 'data.upcoming');

        $this->deleteJson("/api/reminders/{$id}")->assertNoContent();
        $this->assertSoftDeleted('reminders', ['id' => $id]);
    }

    public function test_valida_datos_del_recordatorio(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/reminders', [])->assertUnprocessable()->assertJsonValidationErrors(['title', 'remind_on']);
        $this->postJson('/api/reminders', ['title' => 'x', 'remind_on' => today()->toDateString(), 'client_id' => 9999])
            ->assertUnprocessable()->assertJsonValidationErrors('client_id');
        $this->getJson('/api/agenda?days=999')->assertUnprocessable()->assertJsonValidationErrors('days');
    }
}
