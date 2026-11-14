<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Receipt;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Comprobantes internos de pago. No son facturas y no tienen validez fiscal.
 */
class ReceiptController extends Controller
{
    /**
     * Emite el comprobante de un pago cobrado. Si ya tiene uno vigente lo devuelve;
     * si el último fue anulado, emite uno nuevo con el número siguiente.
     */
    public function store(Payment $payment): JsonResponse
    {
        if ($payment->paid_at === null) {
            throw ValidationException::withMessages(['payment' => 'Solo se puede emitir comprobante de un pago cobrado.']);
        }

        // El número es correlativo; si dos pedidos chocan, el índice único lo detecta y se reintenta.
        $result = null;
        for ($attempt = 0; $attempt < 3 && ! $result; $attempt++) {
            try {
                $result = DB::transaction(function () use ($payment) {
                    // Se bloquea el pago para que dos pedidos simultáneos no emitan dos comprobantes vigentes.
                    $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

                    $active = $locked->receipts()->whereNull('voided_at')->first();
                    if ($active) {
                        return [$active, false];
                    }

                    $locked->load(['client', 'project']);

                    return [Receipt::create([
                        'payment_id' => $locked->id,
                        'number' => (int) Receipt::max('number') + 1,
                        'client_name' => $locked->client->display_name,
                        'client_tax_id' => $locked->client->tax_id,
                        'concept' => $locked->concept,
                        'project_name' => $locked->project ? ($locked->project->name ?: $locked->project->service_type->label()) : null,
                        'amount' => $locked->amount,
                        'paid_at' => $locked->paid_at,
                        'method' => $locked->method,
                        'issued_at' => now(),
                    ]), true];
                });
            } catch (UniqueConstraintViolationException $e) {
                // Chocó el número con otro pedido: se reintenta.
            }
        }

        abort_unless($result, 503, 'No se pudo emitir el comprobante. Intentá de nuevo.');

        [$receipt, $created] = $result;

        if ($created) {
            AuditLog::record('receipt.issued', $receipt, ['code' => $receipt->code, 'payment_id' => $payment->id]);
        }

        return $this->show($receipt, $created ? 201 : 200);
    }

    public function show(Receipt $receipt, int $status = 200): JsonResponse
    {
        return response()->json(['data' => [
            'id' => $receipt->id,
            'code' => $receipt->code,
            'client_name' => $receipt->client_name,
            'client_tax_id' => $receipt->client_tax_id,
            'concept' => $receipt->concept,
            'project_name' => $receipt->project_name,
            'amount' => (float) $receipt->amount,
            'paid_at' => $receipt->paid_at->toDateString(),
            'method' => $receipt->method,
            'issued_at' => $receipt->issued_at->toIso8601String(),
            'voided_at' => $receipt->voided_at?->toIso8601String(),
            'business' => [
                'name' => config('business.name'),
                'tax_id' => config('business.tax_id'),
                'address' => config('business.address'),
                'contact' => config('business.contact'),
            ],
        ]], $status);
    }

    /** Anula el comprobante (queda registrado como anulado; el número no se reutiliza). */
    public function void(Receipt $receipt): JsonResponse
    {
        if (! $receipt->isVoided()) {
            $receipt->update(['voided_at' => now()]);

            AuditLog::record('receipt.voided', $receipt, ['code' => $receipt->code]);
        }

        return $this->show($receipt->refresh());
    }
}
