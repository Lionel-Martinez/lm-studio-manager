<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
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

class ClientController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) $request->query('search', ''));

        $clients = Client::query()
            ->with(['projects' => fn ($query) => $query->orderBy('entry_date', 'desc')])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15);

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
            }

            return $client;
        });

        AuditLog::record('client.created', $client, ['name' => $client->display_name]);

        return ClientResource::make($client->load('projects'))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Client $client): ClientResource
    {
        return ClientResource::make($client->load('projects'));
    }

    public function update(UpdateClientRequest $request, Client $client): ClientResource
    {
        $client->update($request->validated());

        AuditLog::record('client.updated', $client, ['name' => $client->display_name]);

        return ClientResource::make($client->refresh()->load('projects'));
    }

    /** Baja lógica: el cliente deja de verse pero su historial se conserva y se puede recuperar. */
    public function destroy(Client $client): Response
    {
        $client->delete();

        AuditLog::record('client.deleted', $client, ['name' => $client->display_name]);

        return response()->noContent();
    }
}
