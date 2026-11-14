<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Enums\ServiceType;
use App\Models\Payment;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

/** Resumen por tipo de servicio: proyectos por estado, clientes y plata. */
class ServiceOverviewController extends Controller
{
    public function __invoke(): JsonResponse
    {
        // Solo proyectos de clientes que no fueron eliminados.
        $projects = Project::query()->whereHas('client')->get()->groupBy(fn (Project $p) => $p->service_type->value);

        $payments = Payment::query()->whereHas('client')->whereNotNull('project_id')->with('project')->get()
            ->filter(fn (Payment $p) => $p->project !== null)
            ->groupBy(fn (Payment $p) => $p->project->service_type->value);

        $data = collect(ServiceType::cases())->map(function (ServiceType $type) use ($projects, $payments) {
            $own = $projects->get($type->value, collect());
            $money = $payments->get($type->value, collect());

            return [
                'type' => $type->value,
                'label' => $type->label(),
                'projects' => $own->count(),
                'clients' => $own->pluck('client_id')->unique()->count(),
                'by_status' => collect(ProjectStatus::cases())->map(fn (ProjectStatus $s) => [
                    'status' => $s->value,
                    'label' => $s->label(),
                    'count' => $own->filter(fn (Project $p) => $p->status === $s)->count(),
                ])->values(),
                'late' => $own->filter(fn (Project $p) => $p->status !== ProjectStatus::Delivered && $p->due_date !== null && $p->due_date->isBefore(today()))->count(),
                'collected' => (float) $money->whereNotNull('paid_at')->sum('amount'),
                'pending' => (float) $money->whereNull('paid_at')->sum('amount'),
            ];
        })->values();

        return response()->json(['data' => $data]);
    }
}
