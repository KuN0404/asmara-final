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
        Schema::create('office_agendas', function (Blueprint $table) {
            $table->id();
            $table->dateTime('start_at');
            $table->dateTime('until_at');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('agenda_type');
            $table->string('activity_type');
            $table->string('metting_link')->nullable();
            $table->string('location')->nullable(); //bisa pke table room atau string biasa
            $table->string('attachment')->nullable(); //bisa pke table annouchment atau bisa kosong, buakan table, bisa lebih adri 1
            $table->string('created_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('office_agendas');
    }
};