<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    private const DISTRIBUTION_TOP = 5;

    public function __invoke(): JsonResponse
    {
        $working = [ProjectStatus::InProgress->value, ProjectStatus::Ready->value];
        $waiting = [ProjectStatus::Prospect->value, ProjectStatus::Waiting->value];

        // Proyectos sin entregar, con su cliente (los clientes eliminados no aparecen).
        $projects = Project::query()
            ->with('client')
            ->whereHas('client')
            ->where('status', '!=', ProjectStatus::Delivered->value)
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->get();

        $unpaid = Payment::query()->with('client')->whereHas('client')->whereNull('paid_at');

        $upcoming = (clone $unpaid)->orderBy('due_date')->limit(10)->get();
        $overdue = Payment::withStatus('overdue')->whereHas('client');

        return response()->json(['data' => [
            'kpis' => [
                'monthly_revenue' => (float) Payment::whereNotNull('paid_at')
                    ->whereBetween('paid_at', [today()->startOfMonth(), today()->endOfMonth()])
                    ->sum('amount'),
                'active_clients' => $projects->filter(fn (Project $p) => in_array($p->status->value, $working, true))->pluck('client_id')->unique()->count(),
                'prospects' => $projects->filter(fn (Project $p) => in_array($p->status->value, $waiting, true))->count(),
                'pending_payments' => Payment::withStatus('pending')->count(),
                'overdue_count' => (clone $overdue)->count(),
                'overdue_amount' => (float) (clone $overdue)->sum('amount'),
            ],
            'projects' => $projects->map(fn (Project $p) => [
                'id' => $p->id,
                'client' => $p->client->display_name,
                'service' => $p->service_type->label(),
                'name' => $p->name,
                'status' => $p->status->value,
                'entry_date' => $p->entry_date?->toDateString(),
                'start_date' => $p->estimated_start_date?->toDateString(),
                'due_date' => $p->due_date?->toDateString(),
                'delay_reason' => $p->delay_reason,
            ])->values(),
            'payments' => $upcoming->map(fn (Payment $p) => [
                'id' => $p->id,
                'client' => $p->client->display_name,
                'concept' => $p->concept,
                'due_date' => $p->due_date->toDateString(),
                'amount' => (float) $p->amount,
            ])->values(),
            'revenue' => $this->revenueByMonth(6),
            'distribution' => $this->distribution(),
        ]]);
    }

    /** Cobros acreditados por mes, de los últimos `$months` meses (el último es el actual). */
    private function revenueByMonth(int $months): array
    {
        $from = today()->startOfMonth()->subMonths($months - 1);

        $totals = Payment::whereNotNull('paid_at')
            ->where('paid_at', '>=', $from)
            ->get(['amount', 'paid_at'])
            ->groupBy(fn (Payment $p) => $p->paid_at->format('Y-m'))
            ->map(fn ($group) => (float) $group->sum('amount'));

        return collect(range(0, $months - 1))
            ->map(fn (int $i) => $from->copy()->addMonths($i)->format('Y-m'))
            ->map(fn (string $month) => ['month' => $month, 'value' => $totals[$month] ?? 0.0])
            ->all();
    }

    /** Clientes por departamento o provincia: los más frecuentes, el resto agrupado y los que no tienen ubicación aparte. */
    private function distribution(): array
    {
        $counts = Client::query()
            ->with('region:id,name')
            ->get(['id', 'region_id'])
            ->groupBy(fn (Client $c) => $c->region?->name ?? 'Sin ubicación')
            ->map->count()
            ->sortDesc();

        $named = $counts->except('Sin ubicación');
        $top = $named->take(self::DISTRIBUTION_TOP);
        $others = $named->slice(self::DISTRIBUTION_TOP)->sum();

        $points = $top->map(fn (int $n, string $label) => ['label' => $label, 'value' => $n])->values();

        if ($others > 0) {
            $points->push(['label' => 'Otras', 'value' => $others]);
        }
        if (($counts['Sin ubicación'] ?? 0) > 0) {
            $points->push(['label' => 'Sin ubicación', 'value' => $counts['Sin ubicación']]);
        }

        return $points->all();
    }
}
