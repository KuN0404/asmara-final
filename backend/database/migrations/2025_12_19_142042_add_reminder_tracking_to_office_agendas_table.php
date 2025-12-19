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
            $table->unsignedInteger('reminder_count')->default(0)->after('attachment_links');
            $table->timestamp('last_reminder_at')->nullable()->after('reminder_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('office_agendas', function (Blueprint $table) {
            $table->dropColumn(['reminder_count', 'last_reminder_at']);
        });
    }
};

