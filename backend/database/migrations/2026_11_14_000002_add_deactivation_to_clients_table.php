<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable()->after('commercial_status');
            $table->string('deactivation_reason', 30)->nullable()->after('deactivated_at');
            $table->string('deactivation_note', 300)->nullable()->after('deactivation_reason');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['deactivated_at', 'deactivation_reason', 'deactivation_note']);
        });
    }
};
