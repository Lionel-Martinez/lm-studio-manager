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

class PaymentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', 'in:pending,overdue,paid'],
            'client_id' => ['nullable', 'integer'],
        ]);

        $payments = Payment::query()
            ->with(['client', 'project'])
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

        return PaymentResource::make($payment->load(['client', 'project']))->response()->setStatusCode(201);
    }

    public function update(UpdatePaymentRequest $request, Payment $payment): PaymentResource
    {
        $payment->update($request->validated());

        AuditLog::record('payment.updated', $payment, ['client_id' => $payment->client_id, 'status' => $payment->status]);

        return PaymentResource::make($payment->load(['client', 'project']));
    }

    public function destroy(Payment $payment): Response
    {
        $payment->delete();

        AuditLog::record('payment.deleted', $payment, ['client_id' => $payment->client_id]);

        return response()->noContent();
    }
}
