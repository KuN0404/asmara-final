<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('office_agendas', function (Blueprint $table) {
            $table->foreignId('updated_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('updated_at_by_user')->nullable()->after('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('office_agendas', function (Blueprint $table) {
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['updated_by', 'updated_at_by_user']);
        });
    }
};

