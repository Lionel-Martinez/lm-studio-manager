<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_crea_una_nota_general_y_una_de_cliente(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);

        $this->postJson('/api/notes', ['title' => 'Precios 2026', 'body' => "# Lista\n- Menú QR"])
            ->assertCreated()->assertJsonPath('data.client_id', null);

        $this->postJson('/api/notes', ['client_id' => $client->id, 'title' => 'Reunión', 'body' => 'Quiere logo nuevo'])
            ->assertCreated()->assertJsonPath('data.client_name', 'Ana Ruiz');
    }

    public function test_valida_titulo_contenido_y_proyecto(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        $other = Client::create(['first_name' => 'Otro', 'last_name' => 'X']);
        $project = $other->projects()->create(['service_type' => 'web_app', 'status' => 'waiting', 'entry_date' => today()]);

        $this->postJson('/api/notes', ['client_id' => $client->id, 'project_id' => $project->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['title', 'body', 'project_id']);
    }

    public function test_busca_en_titulo_y_contenido_y_filtra_generales(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        Note::create(['title' => 'Plantilla contrato', 'body' => 'Cláusula de entrega']);
        Note::create(['client_id' => $client->id, 'title' => 'Llamada', 'body' => 'Pidió descuento']);

        $this->getJson('/api/notes?search=contrato')->assertJsonCount(1, 'data');
        $this->getJson('/api/notes?search=descuento')->assertJsonCount(1, 'data');
        $this->getJson('/api/notes?scope=general')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Plantilla contrato');
        $this->getJson("/api/notes?client_id={$client->id}")->assertJsonCount(1, 'data');
    }

    public function test_edita_y_elimina_con_baja_logica(): void
    {
        $note = Note::create(['title' => 'A', 'body' => 'b']);

        $this->putJson("/api/notes/{$note->id}", ['title' => 'Nuevo', 'body' => 'texto'])->assertOk()->assertJsonPath('data.title', 'Nuevo');
        $this->deleteJson("/api/notes/{$note->id}")->assertNoContent();
        $this->assertSoftDeleted('notes', ['id' => $note->id]);
    }
}
