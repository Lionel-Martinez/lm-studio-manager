<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function payment(array $overrides = []): Payment
    {
        $client = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz', 'company_name' => 'Café Pepe', 'tax_id' => '20-12345678-9']);

        return Payment::create(array_merge([
            'client_id' => $client->id,
            'concept' => 'Seña menú digital',
            'amount' => 50000,
            'due_date' => today(),
            'paid_at' => today(),
            'method' => 'Transferencia',
        ], $overrides));
    }

    public function test_emite_un_comprobante_de_un_pago_cobrado(): void
    {
        $payment = $this->payment();

        $this->postJson("/api/payments/{$payment->id}/receipt")
            ->assertCreated()
            ->assertJsonPath('data.code', 'C-000001')
            ->assertJsonPath('data.client_name', 'Café Pepe')
            ->assertJsonPath('data.amount', 50000)
            ->assertJsonPath('data.business.name', 'LM Studio');

        $this->assertDatabaseHas('audit_logs', ['event' => 'receipt.issued']);
    }

    public function test_no_emite_comprobante_de_un_pago_pendiente(): void
    {
        $payment = $this->payment(['paid_at' => null]);

        $this->postJson("/api/payments/{$payment->id}/receipt")->assertUnprocessable();
    }

    public function test_emitir_dos_veces_devuelve_el_mismo_comprobante(): void
    {
        $payment = $this->payment();

        $first = $this->postJson("/api/payments/{$payment->id}/receipt")->json('data.id');
        $this->postJson("/api/payments/{$payment->id}/receipt")->assertOk()->assertJsonPath('data.id', $first);
        $this->assertDatabaseCount('receipts', 1);
    }

    public function test_los_numeros_son_correlativos(): void
    {
        $a = $this->payment();
        $b = Payment::create(['client_id' => $a->client_id, 'concept' => 'Cuota', 'amount' => 1000, 'due_date' => today(), 'paid_at' => today()]);

        $this->postJson("/api/payments/{$a->id}/receipt")->assertJsonPath('data.code', 'C-000001');
        $this->postJson("/api/payments/{$b->id}/receipt")->assertJsonPath('data.code', 'C-000002');
    }

    public function test_el_comprobante_conserva_los_datos_aunque_cambie_el_cliente(): void
    {
        $payment = $this->payment();
        $id = $this->postJson("/api/payments/{$payment->id}/receipt")->json('data.id');

        $payment->client->update(['company_name' => 'Otro nombre']);

        $this->getJson("/api/receipts/{$id}")->assertJsonPath('data.client_name', 'Café Pepe');
    }

    public function test_con_comprobante_no_se_puede_cambiar_el_importe_ni_eliminar_el_pago(): void
    {
        $payment = $this->payment();
        $this->postJson("/api/payments/{$payment->id}/receipt")->assertCreated();

        $this->putJson("/api/payments/{$payment->id}", [
            'concept' => 'Seña menú digital', 'amount' => 99999, 'due_date' => today()->toDateString(),
            'paid_at' => today()->toDateString(), 'method' => 'Transferencia',
        ])->assertUnprocessable();

        $this->deleteJson("/api/payments/{$payment->id}")->assertUnprocessable();

        // Cambiar algo que no figura en el comprobante sí se puede.
        $this->putJson("/api/payments/{$payment->id}", [
            'concept' => 'Seña menú digital', 'amount' => 50000, 'due_date' => today()->addDay()->toDateString(),
            'paid_at' => today()->toDateString(), 'method' => 'Transferencia', 'notes' => 'ok',
        ])->assertOk();
    }

    public function test_anular_libera_el_pago_y_el_numero_no_se_reutiliza(): void
    {
        $payment = $this->payment();
        $id = $this->postJson("/api/payments/{$payment->id}/receipt")->json('data.id');

        $this->postJson("/api/receipts/{$id}/void")->assertOk()->assertJsonPath('data.voided_at', fn ($v) => $v !== null);
        $this->deleteJson("/api/payments/{$payment->id}")->assertNoContent();

        $this->assertDatabaseHas('receipts', ['id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'receipt.voided']);
    }

    public function test_despues_de_anular_se_emite_uno_nuevo_con_otro_numero(): void
    {
        $payment = $this->payment();
        $first = $this->postJson("/api/payments/{$payment->id}/receipt")->assertCreated()->json('data');
        $this->postJson("/api/receipts/{$first['id']}/void")->assertOk();

        $second = $this->postJson("/api/payments/{$payment->id}/receipt")->assertCreated()->json('data');

        $this->assertNotSame($first['id'], $second['id']);
        $this->assertNotSame($first['code'], $second['code']);
        $this->assertNull($second['voided_at']);
        $this->assertSame(2, $payment->receipts()->count());

        // Con uno vigente, volver a pedir devuelve ese mismo.
        $this->postJson("/api/payments/{$payment->id}/receipt")->assertOk()->assertJsonPath('data.id', $second['id']);
        $this->assertSame(2, $payment->receipts()->count());

        // El pago queda bloqueado de nuevo por el comprobante vigente.
        $this->deleteJson("/api/payments/{$payment->id}")->assertUnprocessable();
    }

    public function test_exige_sesion(): void
    {
        $payment = $this->payment();
        $this->app['auth']->forgetGuards();

        $this->postJson("/api/payments/{$payment->id}/receipt")->assertUnauthorized();
        $this->getJson('/api/receipts/1')->assertUnauthorized();
    }
}
