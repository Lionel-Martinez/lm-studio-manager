<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Country;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeoApiTest extends TestCase
{
    use RefreshDatabase;

    private Country $ar;
    private Region $salta;
    private Region $oran;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());

        $this->ar = Country::create(['name' => 'Argentina', 'iso2' => 'AR']);
        $this->salta = Region::create(['country_id' => $this->ar->id, 'name' => 'Salta', 'region_type' => 'province']);
        $this->oran = Region::create(['country_id' => $this->ar->id, 'parent_id' => $this->salta->id, 'name' => 'Orán', 'region_type' => 'department']);
    }

    public function test_lista_paises_provincias_y_departamentos(): void
    {
        $this->getJson('/api/geo/countries')->assertOk()->assertJsonPath('data.0.name', 'Argentina');

        $this->getJson("/api/geo/regions?country_id={$this->ar->id}")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Salta');

        $this->getJson("/api/geo/regions?country_id={$this->ar->id}&parent_id={$this->salta->id}")
            ->assertOk()->assertJsonPath('data.0.name', 'Orán');
    }

    public function test_exige_pais_para_listar_regiones(): void
    {
        $this->getJson('/api/geo/regions')->assertUnprocessable()->assertJsonValidationErrors('country_id');
    }

    public function test_el_cliente_guarda_ubicacion_y_la_devuelve_completa(): void
    {
        $this->postJson('/api/clients', [
            'first_name' => 'Ana', 'last_name' => 'Ruiz',
            'country_id' => $this->ar->id, 'region_id' => $this->oran->id,
        ])->assertCreated()
            ->assertJsonPath('data.location_label', 'Orán, Salta')
            ->assertJsonPath('data.province_id', $this->salta->id)
            ->assertJsonPath('data.country_name', 'Argentina');
    }

    public function test_rechaza_una_region_de_otro_pais(): void
    {
        $chile = Country::create(['name' => 'Chile', 'iso2' => 'CL']);

        $this->postJson('/api/clients', [
            'first_name' => 'Ana', 'last_name' => 'Ruiz',
            'country_id' => $chile->id, 'region_id' => $this->oran->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('region_id');
    }

    public function test_una_provincia_sola_tambien_se_muestra(): void
    {
        $client = Client::create(['first_name' => 'Leo', 'last_name' => 'Paz', 'country_id' => $this->ar->id, 'region_id' => $this->salta->id]);

        $this->getJson("/api/clients/{$client->id}")->assertJsonPath('data.location_label', 'Salta')->assertJsonPath('data.province_id', $this->salta->id);
    }
}
