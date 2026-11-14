<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Un pago puede tener varios comprobantes en el historial (los anulados y uno vigente). */
return new class extends Migration
{
    public function up(): void
    {
        // Primero el índice común: la clave foránea necesita uno mientras se quita el único.
        Schema::table('receipts', function (Blueprint $table) {
            $table->index('payment_id');
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->dropUnique(['payment_id']);
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->unique('payment_id');
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->dropIndex(['payment_id']);
        });
    }
};
