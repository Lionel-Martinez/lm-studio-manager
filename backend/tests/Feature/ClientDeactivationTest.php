<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientDeactivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_exige_sesion(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);

        $this->postJson("/api/clients/{$client->id}/deactivate", ['reason' => 'non_payment'])->assertUnauthorized();
        $this->postJson("/api/clients/{$client->id}/reactivate")->assertUnauthorized();
    }

    public function test_desactiva_con_motivo_sin_borrar_nada(): void
    {
        $this->actingAs(User::factory()->create());
        $client = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        Payment::create(['client_id' => $client->id, 'concept' => 'Saldo', 'amount' => 100, 'due_date' => '2026-01-01']);

        $this->postJson("/api/clients/{$client->id}/deactivate", ['reason' => 'non_payment', 'note' => 'Debe 2 cuotas'])
            ->assertOk()
            ->assertJsonPath('data.commercial_status', 'inactive')
            ->assertJsonPath('data.deactivation_reason', 'non_payment')
            ->assertJsonPath('data.deactivation_reason_label', 'Falta de pago')
            ->assertJsonPath('data.deactivation_note', 'Debe 2 cuotas');

        $this->assertNotSoftDeleted('clients', ['id' => $client->id]);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('audit_logs', ['event' => 'client.deactivated']);
    }

    public function test_valida_el_motivo(): void
    {
        $this->actingAs(User::factory()->create());
        $client = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);

        $this->postJson("/api/clients/{$client->id}/deactivate", [])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson("/api/clients/{$client->id}/deactivate", ['reason' => 'otra'])->assertUnprocessable()->assertJsonValidationErrors('reason');
    }

    public function test_reactiva_y_limpia_el_motivo(): void
    {
        $this->actingAs(User::factory()->create());
        $client = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        $this->postJson("/api/clients/{$client->id}/deactivate", ['reason' => 'non_payment'])->assertOk();

        $this->postJson("/api/clients/{$client->id}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.commercial_status', 'active')
            ->assertJsonPath('data.deactivation_reason', null)
            ->assertJsonPath('data.deactivated_at', null);
    }

    public function test_filtra_la_lista_por_estado(): void
    {
        $this->actingAs(User::factory()->create());
        $ana = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        Client::create(['first_name' => 'Leo', 'last_name' => 'Paz']);
        $this->postJson("/api/clients/{$ana->id}/deactivate", ['reason' => 'no_activity'])->assertOk();

        $this->getJson('/api/clients?status=inactive')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ana->id);
        $this->getJson('/api/clients')->assertJsonCount(2, 'data');
        $this->getJson('/api/clients?status=xyz')->assertUnprocessable();
    }

    public function test_la_agenda_oculta_entregas_de_inactivos_pero_mantiene_sus_cobros(): void
    {
        $this->actingAs(User::factory()->create());
        $ana = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        $ana->projects()->create(['service_type' => 'web_app', 'status' => 'in_progress', 'entry_date' => '2026-01-01', 'due_date' => today()->addDay()->toDateString()]);
        Payment::create(['client_id' => $ana->id, 'concept' => 'Saldo', 'amount' => 100, 'due_date' => today()->addDay()->toDateString()]);

        $this->postJson("/api/clients/{$ana->id}/deactivate", ['reason' => 'non_payment'])->assertOk();

        $this->getJson('/api/agenda')->assertJsonCount(1, 'data.upcoming')->assertJsonPath('data.upcoming.0.type', 'payment');
    }
}
