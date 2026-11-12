<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Carga los datos geográficos de referencia, en orden:
     * países → provincias (y CABA) → departamentos.
     */
    public function run(): void
    {
        $this->call([
            CountrySeeder::class,
            ProvinceSeeder::class,
            DepartmentSeeder::class,
        ]);
    }
}
