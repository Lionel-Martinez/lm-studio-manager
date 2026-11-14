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
    /** Emite el comprobante de un pago cobrado (si ya tiene uno, lo devuelve). */
    public function store(Payment $payment): JsonResponse
    {
        if ($payment->paid_at === null) {
            throw ValidationException::withMessages(['payment' => 'Solo se puede emitir comprobante de un pago cobrado.']);
        }

        $existing = $payment->receipt;
        if ($existing) {
            return $this->show($existing);
        }

        $payment->load(['client', 'project']);

        // El número es correlativo; si dos pedidos chocan, el índice único lo detecta y se reintenta.
        $receipt = null;
        for ($attempt = 0; $attempt < 3 && ! $receipt; $attempt++) {
            try {
                $receipt = DB::transaction(fn () => Receipt::create([
                    'payment_id' => $payment->id,
                    'number' => (int) Receipt::max('number') + 1,
                    'client_name' => $payment->client->display_name,
                    'client_tax_id' => $payment->client->tax_id,
                    'concept' => $payment->concept,
                    'project_name' => $payment->project ? ($payment->project->name ?: $payment->project->service_type->label()) : null,
                    'amount' => $payment->amount,
                    'paid_at' => $payment->paid_at,
                    'method' => $payment->method,
                    'issued_at' => now(),
                ]));
            } catch (UniqueConstraintViolationException $e) {
                // Si el choque fue por el pago (otro pedido lo emitió primero), se devuelve ese.
                if ($existing = Receipt::where('payment_id', $payment->id)->first()) {
                    return $this->show($existing);
                }
            }
        }

        abort_unless($receipt, 503, 'No se pudo emitir el comprobante. Intentá de nuevo.');

        AuditLog::record('receipt.issued', $receipt, ['code' => $receipt->code, 'payment_id' => $payment->id]);

        return $this->show($receipt, 201);
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
