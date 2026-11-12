
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('official_name')->nullable();

            // Códigos internacionales ISO 3166.
            $table->char('iso2', 2)->unique();
            $table->char('iso3', 3)->nullable()->unique();

            $table->string('phone_code', 10)->nullable();
            $table->char('currency_code', 3)->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
