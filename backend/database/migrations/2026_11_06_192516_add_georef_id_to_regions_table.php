<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->string('georef_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('regions', 'georef_id')) {
            Schema::table('regions', function (Blueprint $table) {
                $table->dropUnique(['georef_id']);
                $table->dropColumn('georef_id');
            });
        }
    }
};
