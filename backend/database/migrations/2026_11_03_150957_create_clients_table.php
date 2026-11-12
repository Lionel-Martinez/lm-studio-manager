
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();

            // Datos personales o comerciales.
            $table->string('first_name');
            $table->string('last_name');
            $table->string('company_name')->nullable();
            $table->string('tax_id')->nullable();

            // Contacto.
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('website')->nullable();

            // Ubicación geográfica.
            $table->foreignId('country_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('region_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('locality_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Seguimiento comercial.
            $table->date('first_contact_at')->nullable();
            $table->string('source')->nullable();

            // person o business.
            $table->string('client_type')->default('person');

            // new, contacted, proposal_sent, etc.
            $table->string('commercial_status')->default('new');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('commercial_status');
            $table->index('client_type');
            $table->index(['country_id', 'region_id', 'locality_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
