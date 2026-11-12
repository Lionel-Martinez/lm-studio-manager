<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_un_cliente_sin_proyecto(): void
    {
        $response = $this->postJson('/api/clients', [
            'name' => 'Cafetería Don Pepe',
            'phone' => '3878 123456',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Cafetería Don Pepe')
            ->assertJsonPath('data.projects', []);

        $this->assertDatabaseHas('clients', ['name' => 'Cafetería Don Pepe']);
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_crea_un_cliente_con_su_primer_proyecto_y_fechas(): void
    {
        $response = $this->postJson('/api/clients', [
            'name' => 'Ferretería Norte',
            'project' => [
                'service_type' => 'web_app',
                'status' => 'waiting',
                'entry_date' => '2026-11-03',
                'estimated_start_date' => '2026-11-16',
                'due_date' => '2026-12-19',
                'delay_reason' => 'Falta el catálogo del cliente',
            ],
        ]);

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
        $response = $this->postJson('/api/clients', [
            'name' => 'Panadería La Espiga',
            'project' => ['service_type' => 'digital_menu'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.projects.0.status', 'prospect')
            ->assertJsonPath('data.projects.0.entry_date', now()->toDateString());
    }

    public function test_el_nombre_es_obligatorio(): void
    {
        $this->postJson('/api/clients', ['email' => 'a@b.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_si_se_envia_proyecto_el_servicio_es_obligatorio(): void
    {
        $this->postJson('/api/clients', [
            'name' => 'Cliente',
            'project' => ['status' => 'waiting'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project.service_type']);

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_la_entrega_no_puede_ser_anterior_al_inicio(): void
    {
        $this->postJson('/api/clients', [
            'name' => 'Cliente',
            'project' => [
                'service_type' => 'static_site',
                'estimated_start_date' => '2026-12-10',
                'due_date' => '2026-12-01',
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project.due_date']);
    }

    public function test_se_puede_informar_la_entrega_sin_inicio(): void
    {
        $this->postJson('/api/clients', [
            'name' => 'Cliente',
            'project' => [
                'service_type' => 'static_site',
                'due_date' => '2026-12-01',
            ],
        ])->assertCreated();
    }

    public function test_rechaza_un_servicio_o_estado_inexistente(): void
    {
        $this->postJson('/api/clients', [
            'name' => 'Cliente',
            'project' => ['service_type' => 'hosting', 'status' => 'archivado'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project.service_type', 'project.status']);
    }

    public function test_rechaza_un_correo_invalido(): void
    {
        $this->postJson('/api/clients', ['name' => 'Cliente', 'email' => 'no-es-un-correo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_lista_y_busca_clientes(): void
    {
        Client::create(['name' => 'Gimnasio Vital', 'city' => 'Orán']);
        Client::create(['name' => 'Estudio Rojas', 'city' => 'Salta']);

        $this->getJson('/api/clients')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/clients?search=vital')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Gimnasio Vital');
    }

    public function test_muestra_un_cliente_con_sus_proyectos(): void
    {
        $client = Client::create(['name' => 'Gimnasio Vital']);
        $client->projects()->create([
            'service_type' => 'mobile_app',
            'status' => 'in_progress',
            'entry_date' => '2026-10-12',
        ]);

        $this->getJson("/api/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Gimnasio Vital')
            ->assertJsonCount(1, 'data.projects')
            ->assertJsonPath('data.projects.0.status_label', 'En desarrollo');
    }
}
