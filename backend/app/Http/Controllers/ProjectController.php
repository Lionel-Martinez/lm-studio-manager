<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    /** Agrega un proyecto a un cliente que ya existe. */
    public function store(StoreProjectRequest $request, Client $client): JsonResponse
    {
        $data = $request->validated();

        $project = $client->projects()->create($data + [
            'status' => ProjectStatus::Prospect->value,
            'entry_date' => now()->toDateString(),
        ])->refresh();

        $client->markActiveIfWorking();
        AuditLog::record('project.created', $project, ['client_id' => $client->id]);

        return ProjectResource::make($project)->response()->setStatusCode(201);
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $project->update($request->validated());
        $project->client?->markActiveIfWorking();

        AuditLog::record('project.updated', $project, ['client_id' => $project->client_id, 'status' => $project->status->value]);

        return ProjectResource::make($project->refresh());
    }

    /** Baja lógica, igual que con los clientes. */
    public function destroy(Project $project): Response
    {
        $project->delete();

        AuditLog::record('project.deleted', $project, ['client_id' => $project->client_id]);

        return response()->noContent();
    }
}
