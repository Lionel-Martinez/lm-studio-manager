<?php

namespace App\Http\Controllers;

use App\Enums\CommercialStatus;
use App\Enums\ProjectStatus;
use App\Http\Requests\DeactivateClientRequest;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\AuditLog;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->validate(['status' => ['nullable', Rule::enum(CommercialStatus::class)]])['status'] ?? null;

        $clients = Client::query()
            ->with(['country', 'region.parent', 'locality', 'projects' => fn ($query) => $query->orderBy('entry_date', 'desc')])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($query, $value) => $query->where('commercial_status', $value))
            ->latest()
            ->paginate(min(max((int) $request->query('per_page', 15), 1), 100));

        return ClientResource::collection($clients);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $data = $request->validated();

        $client = DB::transaction(function () use ($data) {
            $clientData = Arr::except($data, ['project']);
            $clientData['first_contact_at'] ??= now()->toDateString();

            // refresh() trae los valores por defecto de la base (tipo de cliente, estado comercial).
            $client = Client::create($clientData)->refresh();

            if (! empty($data['project'])) {
                $project = $data['project'];

                $client->projects()->create([
                    'service_type' => $project['service_type'],
                    'name' => $project['name'] ?? null,
                    'status' => $project['status'] ?? ProjectStatus::Prospect->value,
                    'entry_date' => $project['entry_date'] ?? now()->toDateString(),
                    'estimated_start_date' => $project['estimated_start_date'] ?? null,
                    'due_date' => $project['due_date'] ?? null,
                    'delay_reason' => $project['delay_reason'] ?? null,
                ]);

                $client->markActiveIfWorking();
            }

            return $client;
        });

        AuditLog::record('client.created', $client, ['name' => $client->display_name]);

        return ClientResource::make($client->load(['projects', 'country', 'region.parent', 'locality']))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Client $client): ClientResource
    {
        return ClientResource::make($client->load(['projects', 'country', 'region.parent', 'locality']));
    }

    public function update(UpdateClientRequest $request, Client $client): ClientResource
    {
        $data = $request->validated();

        // Si se lo vuelve a poner activo/nuevo a mano, ya no corresponde el motivo de desactivación.
        if (isset($data['commercial_status']) && $data['commercial_status'] !== CommercialStatus::Inactive->value) {
            $data += ['deactivated_at' => null, 'deactivation_reason' => null, 'deactivation_note' => null];
        }

        $client->update($data);

        AuditLog::record('client.updated', $client, ['name' => $client->display_name]);

        return ClientResource::make($client->refresh()->load(['projects', 'country', 'region.parent', 'locality']));
    }

    /** Desactivar no borra nada: el cliente sigue en la lista, marcado como inactivo y con su motivo. */
    public function deactivate(DeactivateClientRequest $request, Client $client): ClientResource
    {
        $data = $request->validated();

        $client->update([
            'commercial_status' => CommercialStatus::Inactive,
            'deactivated_at' => now(),
            'deactivation_reason' => $data['reason'],
            'deactivation_note' => $data['note'] ?? null,
        ]);

        AuditLog::record('client.deactivated', $client, ['name' => $client->display_name, 'reason' => $data['reason']]);

        return ClientResource::make($client->refresh()->load(['projects', 'country', 'region.parent', 'locality']));
    }

    public function reactivate(Client $client): ClientResource
    {
        $client->update([
            'commercial_status' => CommercialStatus::Active,
            'deactivated_at' => null,
            'deactivation_reason' => null,
            'deactivation_note' => null,
        ]);

        AuditLog::record('client.reactivated', $client, ['name' => $client->display_name]);

        return ClientResource::make($client->refresh()->load(['projects', 'country', 'region.parent', 'locality']));
    }

    /** Baja lógica: el cliente deja de verse pero su historial se conserva y se puede recuperar. */
    public function destroy(Client $client): Response
    {
        $client->delete();

        AuditLog::record('client.deleted', $client, ['name' => $client->display_name]);

        return response()->noContent();
    }
}
