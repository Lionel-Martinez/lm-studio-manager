<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectPricingRequest;
use App\Http\Resources\ProjectResource;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Project;
use App\Support\PaymentPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Precio del proyecto y su plan de pago (pago único, anticipo/saldo o cuotas, más cuota mensual). */
class ProjectPricingController extends Controller
{
    /** Muestra el calendario que resultaría, sin guardar nada. */
    public function preview(ProjectPricingRequest $request, Project $project): JsonResponse
    {
        $draft = $project->replicate()->fill($request->planData());
        $draft->id = $project->id;

        return $this->respond($project, PaymentPlan::schedule($draft));
    }

    /** Guarda el plan y, si se pide, crea (o rehace) los pagos del calendario. */
    public function update(ProjectPricingRequest $request, Project $project): JsonResponse
    {
        $generate = (bool) $request->boolean('generate');

        $generated = DB::transaction(function () use ($request, $project, $generate) {
            $project->update($request->planData());
            $project->refresh();

            if (! $generate) {
                return 0;
            }

            $schedule = PaymentPlan::schedule($project);

            if ($schedule === []) {
                throw ValidationException::withMessages(['total_price' => 'Cargá un precio o una cuota mensual para generar los pagos.']);
            }

            $existing = Payment::where('project_id', $project->id)->whereNotNull('plan_item');

            if ((clone $existing)->whereNotNull('paid_at')->exists()) {
                throw ValidationException::withMessages(['generate' => 'Ya hay pagos de este plan cobrados, así que no se puede rehacer el calendario. Editá los pagos desde la sección Pagos.']);
            }

            // Solo se reemplazan los pagos pendientes que había creado un plan anterior.
            $existing->delete();

            foreach ($schedule as $row) {
                Payment::create([
                    'client_id' => $project->client_id,
                    'project_id' => $project->id,
                    'plan_item' => $row['item'],
                    'concept' => mb_substr($row['concept'].' — '.($project->name ?: $project->service_type->label()), 0, 150),
                    'amount' => $row['amount'],
                    'due_date' => $row['due_date'],
                ]);
            }

            return count($schedule);
        });

        AuditLog::record('project.pricing', $project, ['client_id' => $project->client_id, 'generated_payments' => $generated]);

        return $this->respond($project, PaymentPlan::schedule($project), $generated);
    }

    private function respond(Project $project, array $schedule, ?int $generated = null): JsonResponse
    {
        $plan = Payment::where('project_id', $project->id)->whereNotNull('plan_item');

        return response()->json(['data' => [
            'project' => ProjectResource::make($project->fresh()),
            'schedule' => $schedule,
            'schedule_total' => round(array_sum(array_column($schedule, 'amount')), 2),
            'existing_plan_payments' => (clone $plan)->count(),
            'plan_locked' => (clone $plan)->whereNotNull('paid_at')->exists(),
            'generated' => $generated,
        ]]);
    }
}
