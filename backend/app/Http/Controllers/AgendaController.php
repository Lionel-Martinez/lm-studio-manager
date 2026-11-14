<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Agenda: entregas, inicios, vencimientos de cobro y recordatorios propios,
 * en una sola lista ordenada por fecha.
 */
class AgendaController extends Controller
{
    /** Atrasados (antes de hoy y sin resolver) y próximos `days` días. */
    public function index(Request $request): JsonResponse
    {
        $days = (int) ($request->validate(['days' => ['nullable', 'integer', 'min:1', 'max:365']])['days'] ?? 30);

        $events = $this->events(today()->addDays($days)->toDateString());

        return response()->json(['data' => [
            'overdue' => $events->filter(fn (array $e) => $e['date'] < today()->toDateString())->values(),
            'upcoming' => $events->filter(fn (array $e) => $e['date'] >= today()->toDateString())->values(),
        ]]);
    }

    /** Números para la insignia del menú. */
    public function summary(): JsonResponse
    {
        $events = $this->events(today()->toDateString());
        $today = today()->toDateString();

        return response()->json(['data' => [
            'overdue' => $events->where('date', '<', $today)->count(),
            'today' => $events->where('date', $today)->count(),
        ]]);
    }

    /** @return Collection<int, array<string, mixed>> Pendientes con fecha hasta `$until`, ordenados por fecha. */
    private function events(string $until): Collection
    {
        $events = collect();

        // Entregas: proyectos sin entregar con fecha de entrega.
        Project::query()->with('client')->whereHas('client')
            ->where('status', '!=', ProjectStatus::Delivered->value)
            ->whereNotNull('due_date')->whereDate('due_date', '<=', $until)
            ->get()
            ->each(fn (Project $p) => $events->push([
                'key' => "delivery-{$p->id}", 'type' => 'delivery', 'id' => $p->id,
                'date' => $p->due_date->toDateString(),
                'title' => 'Entrega: '.($p->name ?: $p->service_type->label()),
                'client' => $p->client->display_name, 'amount' => null,
            ]));

        // Inicios: proyectos que todavía no arrancaron y tienen fecha estimada de inicio.
        Project::query()->with('client')->whereHas('client')
            ->whereIn('status', [ProjectStatus::Prospect->value, ProjectStatus::Waiting->value])
            ->whereNotNull('estimated_start_date')->whereDate('estimated_start_date', '<=', $until)
            ->get()
            ->each(fn (Project $p) => $events->push([
                'key' => "start-{$p->id}", 'type' => 'start', 'id' => $p->id,
                'date' => $p->estimated_start_date->toDateString(),
                'title' => 'Inicio: '.($p->name ?: $p->service_type->label()),
                'client' => $p->client->display_name, 'amount' => null,
            ]));

        // Cobros pendientes.
        Payment::query()->with('client')->whereNull('paid_at')->whereDate('due_date', '<=', $until)
            ->get()
            ->each(fn (Payment $p) => $events->push([
                'key' => "payment-{$p->id}", 'type' => 'payment', 'id' => $p->id,
                'date' => $p->due_date->toDateString(),
                'title' => 'Cobro: '.$p->concept,
                'client' => $p->client?->display_name, 'amount' => (float) $p->amount,
            ]));

        // Recordatorios propios sin hacer.
        Reminder::query()->with('client')->whereNull('done_at')->whereDate('remind_on', '<=', $until)
            ->get()
            ->each(fn (Reminder $r) => $events->push([
                'key' => "reminder-{$r->id}", 'type' => 'reminder', 'id' => $r->id,
                'date' => $r->remind_on->toDateString(),
                'title' => $r->title,
                'client' => $r->client?->display_name, 'amount' => null,
                'notes' => $r->notes, 'client_id' => $r->client_id,
            ]));

        return $events->sortBy([['date', 'asc'], ['type', 'asc']])->values();
    }
}
