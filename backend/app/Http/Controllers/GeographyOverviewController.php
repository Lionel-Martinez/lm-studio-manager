<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * Clientes e ingresos cobrados por país, provincia y departamento.
 * client.region_id guarda el lugar más específico (departamento si lo hay, si no la provincia).
 */
class GeographyOverviewController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $collected = Payment::query()->whereNotNull('paid_at')->selectRaw('client_id, SUM(amount) as total')->groupBy('client_id')->pluck('total', 'client_id');

        $clients = Client::query()->with(['country', 'region.parent', 'locality'])->get();

        $located = $clients->filter(fn (Client $c) => $c->country_id !== null);

        $countries = $this->group($located, fn (Client $c) => $c->country_id, fn (Client $c) => ['name' => $c->country?->name ?? '—'], $collected);

        $withProvince = $clients->filter(fn (Client $c) => $c->region !== null);
        $provinces = $this->group(
            $withProvince,
            fn (Client $c) => $c->region->parent_id ?? $c->region->id,
            fn (Client $c) => ['name' => $c->region->parent?->name ?? $c->region->name, 'country_id' => $c->country_id],
            $collected,
        );

        $departments = $this->group(
            $withProvince->filter(fn (Client $c) => $c->region->parent_id !== null),
            fn (Client $c) => $c->region->id,
            fn (Client $c) => ['name' => $c->region->name, 'province_id' => $c->region->parent_id],
            $collected,
        );

        $localities = $this->group(
            $clients->filter(fn (Client $c) => $c->locality !== null),
            fn (Client $c) => $c->locality_id,
            fn (Client $c) => ['name' => $c->locality->name, 'department_id' => $c->locality->region_id, 'municipality' => $c->locality->municipality],
            $collected,
        );

        $unlocated = $clients->filter(fn (Client $c) => $c->country_id === null);

        return response()->json(['data' => [
            'total_clients' => $clients->count(),
            'countries' => $countries,
            'provinces' => $provinces,
            'departments' => $departments,
            'localities' => $localities,
            'unlocated' => [
                'clients' => $unlocated->count(),
                'revenue' => (float) $unlocated->sum(fn (Client $c) => (float) ($collected[$c->id] ?? 0)),
            ],
        ]]);
    }

    /** @return array<int, array<string, mixed>> Ordenado por cantidad de clientes, y luego por ingresos. */
    private function group(Collection $clients, callable $key, callable $attrs, Collection $collected): array
    {
        return $clients->groupBy($key)
            ->map(fn (Collection $group, $id) => ['id' => (int) $id] + $attrs($group->first()) + [
                'clients' => $group->count(),
                'revenue' => (float) $group->sum(fn (Client $c) => (float) ($collected[$c->id] ?? 0)),
            ])
            ->sortBy([['clients', 'desc'], ['revenue', 'desc'], ['name', 'asc']])
            ->values()
            ->all();
    }
}
