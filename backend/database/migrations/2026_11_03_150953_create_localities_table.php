
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('localities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('region_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('postal_code')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['region_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('localities');
    }
};
