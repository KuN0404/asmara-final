<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Modify trigger enum to include new values
        DB::statement("ALTER TABLE whatsapp_logs MODIFY COLUMN `trigger` ENUM('created', 'updated', 'h_minus_1', 'h_day', 'manual', 'approval_request', 'rejected', 'approved', 'cancelled') DEFAULT 'created'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE whatsapp_logs MODIFY COLUMN `trigger` ENUM('created', 'updated', 'h_minus_1', 'h_day', 'manual') DEFAULT 'created'");
    }
};
