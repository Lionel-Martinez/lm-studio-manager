<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Identificador de Georef para no duplicar localidades al volver a importar.
    // (Una migración anterior con nombre parecido agrega esta columna a `regions`, no a `localities`.)
    public function up(): void
    {
        if (Schema::hasColumn('localities', 'georef_id')) {
            return;
        }

        Schema::table('localities', function (Blueprint $table) {
            $table->string('georef_id')->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('localities', 'georef_id')) {
            return;
        }

        Schema::table('localities', function (Blueprint $table) {
            $table->dropUnique(['georef_id']);
            $table->dropColumn('georef_id');
        });
    }
};
