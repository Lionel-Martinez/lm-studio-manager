<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;
use RuntimeException;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/countries.json');

        if (!file_exists($path)) {
            throw new RuntimeException('No se encontró el archivo countries.json');
        }

        $countries = json_decode(
            file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $imported = 0;

        foreach ($countries as $item) {
            if (($item['status'] ?? null) !== 'officially-assigned') {
                continue;
            }

            $iso2 = $item['cca2'] ?? null;
            $iso3 = $item['cca3'] ?? null;

            if (!$iso2 || !$iso3) {
                continue;
            }

            $spanish = $item['translations']['spa'] ?? [];

            $name = $spanish['common']
                ?? $item['name']['common'];

            $officialName = $spanish['official']
                ?? $item['name']['official'];

            $currencyCode = array_key_first(
                $item['currencies'] ?? []
            );

            $phoneRoot = $item['idd']['root'] ?? null;
            $phoneSuffix = $item['idd']['suffixes'][0] ?? '';

            $phoneCode = $phoneRoot
                ? $phoneRoot . $phoneSuffix
                : null;

            Country::updateOrCreate(
                ['iso2' => $iso2],
                [
                    'name' => $name,
                    'official_name' => $officialName,
                    'iso3' => $iso3,
                    'phone_code' => $phoneCode,
                    'currency_code' => $currencyCode,
                    'is_active' => true,
                ]
            );

            $imported++;
        }

        $this->command->info(
            "Países y territorios importados: {$imported}"
        );
    }
}
