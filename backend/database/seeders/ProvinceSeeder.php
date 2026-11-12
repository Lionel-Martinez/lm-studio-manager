<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Region;
use Illuminate\Database\Seeder;
use RuntimeException;

class ProvinceSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/provincias.json');

        if (!file_exists($path)) {
            throw new RuntimeException('No se encontró el archivo provincias.json');
        }

        $data = json_decode(
            file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $country = Country::where('iso2', 'AR')->firstOrFail();

        $imported = 0;

        foreach ($data['provincias'] as $province) {
            $isAutonomousCity = ($province['categoria'] ?? null) === 'Ciudad Autónoma';

            Region::updateOrCreate(
                ['georef_id' => $province['id']],
                [
                    'country_id' => $country->id,
                    'parent_id' => null,
                    'name' => $province['nombre'],
                    'official_name' => $province['nombre_completo'],
                    'region_type' => $isAutonomousCity
                        ? 'autonomous_city'
                        : 'province',
                    'code' => $province['iso_id'] ?? null,
                    'is_active' => true,
                ]
            );

            $imported++;
        }

        $this->command->info(
            "Jurisdicciones argentinas importadas: {$imported}"
        );
    }
}
