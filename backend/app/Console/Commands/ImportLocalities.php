<?php

namespace App\Console\Commands;

use App\Models\Locality;
use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Trae las localidades oficiales de Argentina (API Georef del Estado), una consulta por departamento.
 * Se puede correr más de una vez: actualiza por georef_id y no duplica.
 */
class ImportLocalities extends Command
{
    protected $signature = 'app:import-localities
        {--province= : Nombre o ID Georef de una provincia (por defecto, todas)}
        {--department= : Nombre o ID Georef de un departamento}';

    protected $description = 'Importa localidades y municipios desde la API Georef (datos.gob.ar)';

    private const URL = 'https://apis.datos.gob.ar/georef/api/localidades';

    public function handle(): int
    {
        $departments = Region::query()
            ->where('region_type', 'department')
            ->whereNotNull('georef_id')
            ->when($this->option('province'), function ($query, $value) {
                $query->whereHas('parent', fn ($p) => $p->where('georef_id', $value)->orWhere('name', $value));
            })
            ->when($this->option('department'), fn ($query, $value) => $query->where(fn ($q) => $q->where('georef_id', $value)->orWhere('name', $value)))
            ->orderBy('id')
            ->get();

        if ($departments->isEmpty()) {
            $this->error('No hay departamentos que coincidan. ¿Corriste los seeders de provincias y departamentos?');

            return self::FAILURE;
        }

        $total = 0;
        $failed = [];

        foreach ($departments as $department) {
            try {
                $response = Http::timeout(20)->retry(2, 500)->get(self::URL, [
                    'departamento' => $department->georef_id,
                    'campos' => 'id,nombre,categoria,municipio.nombre',
                    'max' => 5000,
                ])->throw();
            } catch (Throwable $e) {
                $failed[] = $department->name;
                $this->warn("No se pudo traer {$department->name}: {$e->getMessage()}");

                continue;
            }

            foreach ($response->json('localidades', []) as $item) {
                Locality::updateOrCreate(
                    ['georef_id' => (string) $item['id']],
                    [
                        'region_id' => $department->id,
                        'name' => $item['nombre'],
                        'municipality' => $item['municipio']['nombre'] ?? null,
                        'category' => $item['categoria'] ?? null,
                        'is_active' => true,
                    ],
                );
                $total++;
            }

            usleep(150_000); // La API pública limita las consultas por segundo.
        }

        $this->info("Localidades importadas: {$total} (departamentos consultados: {$departments->count()}).");

        if ($failed) {
            $this->warn('Fallaron: '.implode(', ', $failed).'. Volvé a correr el comando para completarlas.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
