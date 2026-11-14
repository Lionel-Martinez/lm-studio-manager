<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Locality;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportLocalitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_importa_localidades_por_departamento_sin_duplicar(): void
    {
        $ar = Country::create(['name' => 'Argentina', 'iso2' => 'AR']);
        $salta = Region::create(['country_id' => $ar->id, 'name' => 'Salta', 'region_type' => 'province', 'georef_id' => '66']);
        $oran = Region::create(['country_id' => $ar->id, 'parent_id' => $salta->id, 'name' => 'Orán', 'region_type' => 'department', 'georef_id' => '66126']);

        Http::fake(['apis.datos.gob.ar/*' => Http::response(['localidades' => [
            ['id' => '66126010', 'nombre' => 'San Ramón de la Nueva Orán', 'categoria' => 'Localidad simple', 'municipio' => ['nombre' => 'San Ramón de la Nueva Orán']],
            ['id' => '66126020', 'nombre' => 'Colonia Santa Rosa', 'categoria' => 'Localidad simple', 'municipio' => ['nombre' => 'Colonia Santa Rosa']],
        ]])]);

        $this->artisan('app:import-localities', ['--province' => 'Salta'])->assertSuccessful();
        $this->artisan('app:import-localities', ['--province' => 'Salta'])->assertSuccessful();

        $this->assertSame(2, Locality::count());
        $this->assertDatabaseHas('localities', ['georef_id' => '66126020', 'region_id' => $oran->id, 'municipality' => 'Colonia Santa Rosa']);
    }

    public function test_avisa_si_falla_la_api(): void
    {
        $ar = Country::create(['name' => 'Argentina', 'iso2' => 'AR']);
        $salta = Region::create(['country_id' => $ar->id, 'name' => 'Salta', 'region_type' => 'province', 'georef_id' => '66']);
        Region::create(['country_id' => $ar->id, 'parent_id' => $salta->id, 'name' => 'Orán', 'region_type' => 'department', 'georef_id' => '66126']);

        Http::fake(['apis.datos.gob.ar/*' => Http::response('error', 500)]);

        $this->artisan('app:import-localities')->assertFailed();
        $this->assertSame(0, Locality::count());
    }
}
