<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Enums\ServiceType;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Informes de un período, calculados con los datos que ya hay cargados. */
class ReportController extends Controller
{
    private const MAX_MONTHS = 60;

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ], [
            'to.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ]);

        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->endOfDay();

        if ($from->diffInMonths($to) > self::MAX_MONTHS) {
            throw ValidationException::withMessages(['to' => 'El período no puede superar los 5 años.']);
        }

        $months = $this->months($from, $to);

        // Cobros acreditados en el período.
        $paid = Payment::query()
            ->with(['project', 'client'])
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from->toDateString(), $to->toDateString()])
            ->get();

        // Lo que vencía en el período y todavía no se cobró.
        $unpaid = Payment::query()
            ->whereNull('paid_at')
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()]);

        $projects = Project::query()
            ->whereHas('client')
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        $waits = $projects
            ->filter(fn (Project $p) => $p->entry_date && $p->estimated_start_date)
            ->map(fn (Project $p) => $p->entry_date->diffInDays($p->estimated_start_date, false));

        return response()->json(['data' => [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'totals' => [
                'collected' => (float) $paid->sum('amount'),
                'pending' => (float) (clone $unpaid)->whereDate('due_date', '>=', today())->sum('amount'),
                'overdue' => (float) (clone $unpaid)->whereDate('due_date', '<', today())->sum('amount'),
                'projects' => $projects->count(),
                'avg_wait_days' => $waits->isEmpty() ? null : round($waits->avg(), 1),
            ],
            'revenue_by_month' => $this->byMonth($months, $paid, fn (Payment $p) => $p->paid_at, fn ($group) => (float) $group->sum('amount')),
            'revenue_by_service' => $this->ranked(
                $paid->groupBy(fn (Payment $p) => $p->project?->service_type->label() ?? 'Sin proyecto'),
                fn ($group) => (float) $group->sum('amount'),
            ),
            'top_clients' => $this->ranked(
                $paid->groupBy(fn (Payment $p) => $p->client?->display_name ?? 'Cliente eliminado'),
                fn ($group) => (float) $group->sum('amount'),
            )->take(5)->values(),
            'projects_by_status' => collect(ProjectStatus::cases())->map(fn (ProjectStatus $s) => [
                'label' => $s->label(),
                'value' => $projects->where('status', $s)->count(),
            ])->values(),
            'projects_by_service' => collect(ServiceType::cases())->map(fn (ServiceType $s) => [
                'label' => $s->label(),
                'value' => $projects->where('service_type', $s)->count(),
            ])->values(),
            'new_clients_by_month' => $this->byMonth(
                $months,
                Client::query()->get(['id', 'first_contact_at', 'created_at']),
                fn (Client $c) => $c->first_contact_at ?? $c->created_at,
                fn ($group) => $group->count(),
                $from,
                $to,
            ),
            'late_projects' => $this->lateProjects(),
        ]]);
    }

    /** @return list<string> Meses (Y-m) entre las dos fechas. */
    private function months(Carbon $from, Carbon $to): array
    {
        $months = [];
        for ($m = $from->copy()->startOfMonth(); $m->lte($to); $m->addMonth()) {
            $months[] = $m->format('Y-m');
        }

        return $months;
    }

    /**
     * Agrupa por mes y completa con 0 los meses sin datos.
     *
     * @return list<array{month:string,value:float|int}>
     */
    private function byMonth(array $months, Collection $items, callable $dateOf, callable $reduce, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $grouped = $items
            ->filter(function ($item) use ($dateOf, $from, $to) {
                $date = $dateOf($item);

                return $date && (! $from || $date->between($from, $to));
            })
            ->groupBy(fn ($item) => $dateOf($item)->format('Y-m'))
            ->map($reduce);

        return array_map(fn (string $m) => ['month' => $m, 'value' => $grouped[$m] ?? 0], $months);
    }

    /** Ordena de mayor a menor. */
    private function ranked(Collection $groups, callable $reduce): Collection
    {
        return $groups
            ->map(fn ($group, $label) => ['label' => (string) $label, 'value' => $reduce($group)])
            ->sortByDesc('value')
            ->values();
    }

    /** Proyectos sin entregar cuya fecha de entrega ya pasó. */
    private function lateProjects(): array
    {
        return Project::query()
            ->with('client')
            ->whereHas('client')
            ->where('status', '!=', ProjectStatus::Delivered->value)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->orderBy('due_date')
            ->limit(10)
            ->get()
            ->map(fn (Project $p) => [
                'id' => $p->id,
                'client' => $p->client->display_name,
                'project' => $p->name ?: $p->service_type->label(),
                'status' => $p->status->label(),
                'due_date' => $p->due_date->toDateString(),
                'days_late' => (int) $p->due_date->diffInDays(today()),
            ])
            ->all();
    }
}
