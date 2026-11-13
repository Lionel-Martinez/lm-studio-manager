<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectApiTest extends TestCase
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

    public function test_agrega_un_proyecto_a_un_cliente_existente(): void
    {
        $client = $this->client();

        $this->postJson("/api/clients/{$client->id}/projects", [
            'service_type' => 'digital_menu',
            'due_date' => '2026-12-20',
            'delay_reason' => 'Falta el logo',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'prospect')
            ->assertJsonPath('data.delay_reason', 'Falta el logo');

        $this->assertDatabaseHas('projects', ['client_id' => $client->id, 'service_type' => 'digital_menu']);
    }

    public function test_valida_servicio_y_fechas(): void
    {
        $client = $this->client();

        $this->postJson("/api/clients/{$client->id}/projects", [
            'estimated_start_date' => '2026-12-10',
            'due_date' => '2026-12-01',
        ])->assertUnprocessable()->assertJsonValidationErrors(['service_type', 'due_date']);
    }

    public function test_cambia_estado_y_fechas_de_un_proyecto(): void
    {
        $client = $this->client();
        $project = $client->projects()->create(['service_type' => 'web_app', 'status' => 'prospect', 'entry_date' => '2026-11-01']);

        $this->putJson("/api/projects/{$project->id}", [
            'service_type' => 'web_app',
            'status' => 'in_progress',
            'estimated_start_date' => '2026-11-15',
            'due_date' => '2026-12-15',
        ])->assertOk()->assertJsonPath('data.status', 'in_progress');

        $this->assertDatabaseHas('audit_logs', ['event' => 'project.updated', 'subject_id' => $project->id]);
    }

    public function test_el_cliente_nuevo_pasa_a_activo_cuando_su_proyecto_esta_en_desarrollo(): void
    {
        $client = $this->client();
        $this->assertSame('new', $client->commercial_status->value);

        $project = $client->projects()->create(['service_type' => 'web_app', 'status' => 'prospect', 'entry_date' => '2026-11-01']);
        $this->putJson("/api/projects/{$project->id}", ['service_type' => 'web_app', 'status' => 'in_progress'])->assertOk();

        $this->assertSame('active', $client->fresh()->commercial_status->value);
    }

    public function test_elimina_un_proyecto_con_baja_logica(): void
    {
        $project = $this->client()->projects()->create(['service_type' => 'static_site', 'status' => 'waiting', 'entry_date' => '2026-11-01']);

        $this->deleteJson("/api/projects/{$project->id}")->assertNoContent();

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_exige_sesion(): void
    {
        $this->app['auth']->forgetGuards();
        $project = $this->client()->projects()->create(['service_type' => 'static_site', 'status' => 'waiting', 'entry_date' => '2026-11-01']);

        $this->deleteJson("/api/projects/{$project->id}")->assertUnauthorized();
    }
}
