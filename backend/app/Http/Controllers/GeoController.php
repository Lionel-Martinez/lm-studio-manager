<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Listas para los selectores de ubicación (país → provincia → departamento). */
class GeoController extends Controller
{
    public function countries(): JsonResponse
    {
        return response()->json(['data' => Country::where('is_active', true)->orderBy('name')->get(['id', 'name', 'iso2'])]);
    }

    /**
     * Sin `parent_id`: las provincias del país. Con `parent_id`: los departamentos de esa provincia.
     */
    public function regions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'parent_id' => ['nullable', 'integer', 'exists:regions,id'],
        ]);

        $regions = Region::query()
            ->where('country_id', $data['country_id'])
            ->where('is_active', true)
            ->where('parent_id', $data['parent_id'] ?? null)
            ->orderBy('name')
            ->get(['id', 'name', 'region_type']);

        return response()->json(['data' => $regions]);
    }
}
