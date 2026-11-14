<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            // Un pago tiene a lo sumo un comprobante.
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('number')->unique();

            // Copia de los datos al emitir: si después cambia el cliente o el pago, el comprobante no se altera.
            $table->string('client_name');
            $table->string('client_tax_id')->nullable();
            $table->string('concept', 150);
            $table->string('project_name', 150)->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('paid_at');
            $table->string('method', 60)->nullable();

            $table->timestamp('issued_at');
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
