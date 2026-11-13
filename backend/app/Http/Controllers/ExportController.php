<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Project;
use App\Support\CsvExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Descargas en CSV de los datos propios (solo con sesión iniciada). */
class ExportController extends Controller
{
    public function clients(): StreamedResponse
    {
        AuditLog::record('export.clients');

        $rows = Client::query()->with(['country', 'region.parent'])->orderBy('id')->lazyById(200)->map(fn (Client $c) => [
            $c->id,
            $c->first_name,
            $c->last_name,
            $c->company_name,
            $c->tax_id,
            $c->email,
            $c->phone,
            $c->whatsapp,
            $c->website,
            $c->country?->name,
            $c->region?->parent?->name ?? $c->region?->name,
            $c->region?->parent ? $c->region->name : null,
            $c->commercial_status?->label(),
            $c->first_contact_at?->toDateString(),
            $c->source,
            $c->notes,
        ]);

        return CsvExport::download('clientes-'.today()->toDateString().'.csv', [
            'ID', 'Nombre', 'Apellido', 'Empresa', 'CUIT/DNI', 'Correo', 'Teléfono', 'WhatsApp', 'Sitio web',
            'País', 'Provincia', 'Departamento', 'Estado', 'Primer contacto', 'Origen', 'Notas',
        ], $rows);
    }

    public function projects(): StreamedResponse
    {
        AuditLog::record('export.projects');

        $rows = Project::query()->with('client')->orderBy('id')->lazyById(200)->map(fn (Project $p) => [
            $p->id,
            $p->client?->display_name,
            $p->service_type->label(),
            $p->name,
            $p->status->label(),
            $p->entry_date?->toDateString(),
            $p->estimated_start_date?->toDateString(),
            $p->due_date?->toDateString(),
            $p->delay_reason,
        ]);

        return CsvExport::download('proyectos-'.today()->toDateString().'.csv', [
            'ID', 'Cliente', 'Servicio', 'Proyecto', 'Estado', 'Ingreso', 'Inicio estimado', 'Entrega estimada', 'Motivo de demora',
        ], $rows);
    }

    public function payments(): StreamedResponse
    {
        AuditLog::record('export.payments');

        $statusLabels = ['paid' => 'Cobrado', 'overdue' => 'Vencido', 'pending' => 'Pendiente'];

        $rows = Payment::query()->with(['client', 'project'])->orderBy('id')->lazyById(200)->map(fn (Payment $p) => [
            $p->id,
            $p->client?->display_name,
            $p->project ? ($p->project->name ?: $p->project->service_type->label()) : null,
            $p->concept,
            // Con coma decimal, como se escribe en Argentina.
            number_format((float) $p->amount, 2, ',', ''),
            $p->due_date->toDateString(),
            $p->paid_at?->toDateString(),
            $statusLabels[$p->status],
            $p->method,
            $p->notes,
        ]);

        return CsvExport::download('pagos-'.today()->toDateString().'.csv', [
            'ID', 'Cliente', 'Proyecto', 'Concepto', 'Importe (ARS)', 'Vence', 'Cobrado el', 'Estado', 'Medio de pago', 'Notas',
        ], $rows);
    }
}
