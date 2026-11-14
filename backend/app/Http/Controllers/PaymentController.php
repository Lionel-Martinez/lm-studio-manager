<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\AuditLog;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', 'in:pending,overdue,paid'],
            'client_id' => ['nullable', 'integer'],
        ]);

        $payments = Payment::query()
            ->with(['client', 'project', 'receipt'])
            ->withStatus($request->query('status'))
            ->when($request->query('client_id'), fn ($q, $id) => $q->where('client_id', $id))
            // Pendientes primero por vencimiento; cobrados por fecha de cobro reciente.
            ->orderByRaw('paid_at IS NOT NULL')
            ->orderBy('due_date')
            ->paginate(20);

        return PaymentResource::collection($payments);
    }

    /** Totales para el encabezado: por cobrar, vencido y cobrado este mes. */
    public function summary(): JsonResponse
    {
        return response()->json(['data' => [
            'pending' => (float) Payment::withStatus('pending')->sum('amount'),
            'overdue' => (float) Payment::withStatus('overdue')->sum('amount'),
            'overdue_count' => Payment::withStatus('overdue')->count(),
            'collected_this_month' => (float) Payment::whereNotNull('paid_at')
                ->whereBetween('paid_at', [today()->startOfMonth(), today()->endOfMonth()])
                ->sum('amount'),
        ]]);
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $payment = Payment::create($request->validated());

        AuditLog::record('payment.created', $payment, ['client_id' => $payment->client_id, 'amount' => (float) $payment->amount]);

        return PaymentResource::make($payment->load(['client', 'project', 'receipt']))->response()->setStatusCode(201);
    }

    public function update(UpdatePaymentRequest $request, Payment $payment): PaymentResource
    {
        $this->ensureNoActiveReceipt($payment, $request->validated());

        $payment->update($request->validated());

        AuditLog::record('payment.updated', $payment, ['client_id' => $payment->client_id, 'status' => $payment->status]);

        return PaymentResource::make($payment->load(['client', 'project', 'receipt']));
    }

    public function destroy(Payment $payment): Response
    {
        $this->ensureNoActiveReceipt($payment);

        $payment->delete();

        AuditLog::record('payment.deleted', $payment, ['client_id' => $payment->client_id]);

        return response()->noContent();
    }

    /**
     * Con un comprobante emitido, los datos que figuran en él no se pueden cambiar
     * (ni borrar el pago): primero hay que anular el comprobante.
     *
     * @param  array<string, mixed>|null  $changes
     */
    private function ensureNoActiveReceipt(Payment $payment, ?array $changes = null): void
    {
        $payment->loadMissing('receipt');

        if (! $payment->hasActiveReceipt()) {
            return;
        }

        $touchesReceipt = $changes === null
            || (string) $changes['concept'] !== (string) $payment->concept
            || (float) $changes['amount'] !== (float) $payment->amount
            || ($changes['paid_at'] ?? null) !== $payment->paid_at?->toDateString()
            || ($changes['method'] ?? null) !== $payment->method;

        if ($touchesReceipt) {
            throw ValidationException::withMessages([
                'concept' => 'Este pago tiene un comprobante emitido. Anulá el comprobante antes de modificarlo o eliminarlo.',
            ]);
        }
    }
}
