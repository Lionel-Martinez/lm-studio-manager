<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // La API exige sesión iniciada.
        $this->actingAs(User::factory()->create());
    }

    /** Datos mínimos de un cliente válido. */
    private function client(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Pedro',
            'last_name' => 'Gómez',
        ], $overrides);
    }

    public function test_crea_un_cliente_sin_proyecto(): void
    {
        $response = $this->postJson('/api/clients', $this->client([
            'company_name' => 'Cafetería Don Pepe',
            'phone' => '3878 123456',
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.display_name', 'Cafetería Don Pepe')
            ->assertJsonPath('data.first_name', 'Pedro')
            ->assertJsonPath('data.projects', []);

        $this->assertDatabaseHas('clients', ['first_name' => 'Pedro', 'company_name' => 'Cafetería Don Pepe']);
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_aplica_los_valores_por_defecto_del_cliente(): void
    {
        $this->postJson('/api/clients', $this->client())
            ->assertCreated()
            ->assertJsonPath('data.display_name', 'Pedro Gómez')
            ->assertJsonPath('data.client_type', 'person')
            ->assertJsonPath('data.commercial_status', 'new')
            ->assertJsonPath('data.first_contact_at', now()->toDateString());
    }

    public function test_crea_un_cliente_con_su_primer_proyecto_y_fechas(): void
    {
        $response = $this->postJson('/api/clients', $this->client([
            'project' => [
                'service_type' => 'web_app',
                'status' => 'waiting',
                'entry_date' => '2026-11-03',
                'estimated_start_date' => '2026-11-16',
                'due_date' => '2026-12-19',
                'delay_reason' => 'Falta el catálogo del cliente',
            ],
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.projects.0.service_type', 'web_app')
            ->assertJsonPath('data.projects.0.status', 'waiting')
            ->assertJsonPath('data.projects.0.entry_date', '2026-11-03')
            ->assertJsonPath('data.projects.0.due_date', '2026-12-19');

        $this->assertDatabaseHas('projects', [
            'service_type' => 'web_app',
            'delay_reason' => 'Falta el catálogo del cliente',
        ]);
    }

    public function test_el_proyecto_nuevo_queda_como_prospecto_y_con_fecha_de_hoy_por_defecto(): void
    {
        $this->postJson('/api/clients', $this->client([
            'project' => ['service_type' => 'digital_menu'],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.projects.0.status', 'prospect')
            ->assertJsonPath('data.projects.0.entry_date', now()->toDateString());
    }

    public function test_nombre_y_apellido_son_obligatorios(): void
    {
        $this->postJson('/api/clients', ['email' => 'a@b.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name']);
    }

    public function test_si_se_envia_proyecto_el_servicio_es_obligatorio(): void
    {
        $this->postJson('/api/clients', $this->client([
            'project' => ['status' => 'waiting'],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project.service_type']);

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_la_entrega_no_puede_ser_anterior_al_inicio(): void
    {
        $this->postJson('/api/clients', $this->client([
            'project' => [
                'service_type' => 'static_site',
                'estimated_start_date' => '2026-12-10',
                'due_date' => '2026-12-01',
            ],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project.due_date']);
    }

    public function test_se_puede_informar_la_entrega_sin_inicio(): void
    {
        $this->postJson('/api/clients', $this->client([
            'project' => [
                'service_type' => 'static_site',
                'due_date' => '2026-12-01',
            ],
        ]))->assertCreated();
    }

    public function test_rechaza_un_servicio_o_estado_inexistente(): void
    {
        $this->postJson('/api/clients', $this->client([
            'project' => ['service_type' => 'hosting', 'status' => 'archivado'],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project.service_type', 'project.status']);
    }

    public function test_rechaza_un_correo_invalido_y_un_tipo_de_cliente_inexistente(): void
    {
        $this->postJson('/api/clients', $this->client([
            'email' => 'no-es-un-correo',
            'client_type' => 'alien',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'client_type']);
    }

    public function test_rechaza_ubicaciones_que_no_existen(): void
    {
        $this->postJson('/api/clients', $this->client([
            'country_id' => 9999,
            'region_id' => 9999,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['country_id', 'region_id']);
    }

    public function test_lista_y_busca_clientes(): void
    {
        Client::create(['first_name' => 'Ana', 'last_name' => 'Vital', 'company_name' => 'Gimnasio Vital']);
        Client::create(['first_name' => 'Luis', 'last_name' => 'Rojas', 'company_name' => 'Estudio Rojas']);

        $this->getJson('/api/clients')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/clients?search=gimnasio')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.display_name', 'Gimnasio Vital');
    }

    public function test_muestra_un_cliente_con_sus_proyectos(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'last_name' => 'Vital']);
        $client->projects()->create([
            'service_type' => 'mobile_app',
            'status' => 'in_progress',
            'entry_date' => '2026-10-12',
        ]);

        $this->getJson("/api/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Ana Vital')
            ->assertJsonCount(1, 'data.projects')
            ->assertJsonPath('data.projects.0.status_label', 'En desarrollo');
    }

    public function test_edita_los_datos_de_un_cliente(): void
    {
        $client = Client::create($this->client());

        $this->putJson("/api/clients/{$client->id}", $this->client(['last_name' => 'Pérez', 'phone' => '387 555']))
            ->assertOk()
            ->assertJsonPath('data.last_name', 'Pérez');

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'last_name' => 'Pérez', 'phone' => '387 555']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'client.updated', 'subject_id' => $client->id]);
    }

    public function test_la_edicion_valida_los_datos(): void
    {
        $client = Client::create($this->client());

        $this->putJson("/api/clients/{$client->id}", ['first_name' => '', 'last_name' => 'X', 'email' => 'no-es-correo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'email']);
    }

    public function test_eliminar_un_cliente_es_una_baja_logica(): void
    {
        $client = Client::create($this->client());

        $this->deleteJson("/api/clients/{$client->id}")->assertNoContent();

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
        $this->getJson("/api/clients/{$client->id}")->assertNotFound();
        $this->getJson('/api/clients')->assertJsonCount(0, 'data');
        $this->assertDatabaseHas('audit_logs', ['event' => 'client.deleted', 'subject_id' => $client->id]);
    }
}
