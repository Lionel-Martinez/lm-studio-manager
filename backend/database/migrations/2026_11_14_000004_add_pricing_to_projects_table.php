<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Importes en pesos argentinos (ARS).
            $table->decimal('total_price', 12, 2)->nullable();
            $table->string('payment_mode', 20)->nullable();
            $table->unsignedTinyInteger('deposit_percent')->nullable();
            $table->unsignedSmallInteger('installments_count')->nullable();
            $table->date('plan_start_date')->nullable();
            $table->decimal('monthly_fee', 12, 2)->nullable();
            $table->unsignedSmallInteger('monthly_months')->nullable();
            $table->unsignedSmallInteger('free_months')->default(0);
            $table->unsignedSmallInteger('trial_value')->default(0);
            $table->string('trial_unit', 10)->nullable();
        });

        Schema::table('payments', function (Blueprint $table) {
            // Identifica los pagos creados por un plan ("deposit", "installment-2", "monthly-1"...).
            $table->string('plan_item', 30)->nullable()->after('project_id');
            $table->index(['project_id', 'plan_item']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'plan_item']);
            $table->dropColumn('plan_item');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['total_price', 'payment_mode', 'deposit_percent', 'installments_count', 'plan_start_date', 'monthly_fee', 'monthly_months', 'free_months', 'trial_value', 'trial_unit']);
        });
    }
};
