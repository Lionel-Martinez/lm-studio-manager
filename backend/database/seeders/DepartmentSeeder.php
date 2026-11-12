<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Region;
use Illuminate\Database\Seeder;
use RuntimeException;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/departamentos.json');

        if (!file_exists($path)) {
            throw new RuntimeException(
                'No se encontró el archivo departamentos.json'
            );
        }

        $data = json_decode(
            file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (!isset($data['departamentos']) || !is_array($data['departamentos'])) {
            throw new RuntimeException(
                'El archivo no contiene una lista válida de departamentos.'
            );
        }

        $country = Country::where('iso2', 'AR')->firstOrFail();

        // Obtenemos las provincias argentinas.
        $provinces = Region::where('country_id', $country->id)
            ->whereIn('region_type', ['province', 'autonomous_city'])
            ->get()
            ->keyBy('georef_id');

        // Validamos las relaciones antes de importar.
        foreach ($data['departamentos'] as $department) {
            $provinceId = (string) $department['provincia']['id'];

            if (!$provinces->has($provinceId)) {
                throw new RuntimeException(
                    "No se encontró la provincia con ID Georef: {$provinceId}"
                );
            }
        }

        $imported = 0;

        foreach ($data['departamentos'] as $department) {
            $provinceId = (string) $department['provincia']['id'];

            $province = $provinces->get($provinceId);

            Region::updateOrCreate(
                ['georef_id' => (string) $department['id']],
                [
                    'country_id' => $country->id,
                    'parent_id' => $province->id,
                    'name' => $department['nombre'],
                    'official_name' => $department['nombre_completo'],
                    'region_type' => 'department',
                    'code' => null,
                    'is_active' => true,
                ]
            );

            $imported++;
        }

        $this->command->info(
            "Departamentos importados: {$imported}"
        );
    }
}
