<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('service_type', 30);
            $table->string('name', 150)->nullable();
            $table->string('status', 30)->default('prospect');
            // Cuándo entró el cliente, cuándo se estima empezar y cuándo entregar.
            $table->date('entry_date');
            $table->date('estimated_start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('delay_reason', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
