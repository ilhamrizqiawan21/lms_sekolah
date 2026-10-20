<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ujian_attempt_event', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ujian_attempt_id')->constrained('ujian_attempt')->cascadeOnDelete();
            // String, bukan enum: menambah jenis baru tidak perlu ALTER kolom enum MariaDB.
            $table->string('jenis', 30);
            $table->dateTime('terjadi_pada');
            // Hanya diisi untuk heartbeat_gap (selisih detik sejak heartbeat sebelumnya).
            $table->unsignedSmallInteger('durasi_detik')->nullable();
            $table->index(['ujian_attempt_id', 'terjadi_pada']);
        });

        Schema::table('ujian_attempt', function (Blueprint $table) {
            $table->dateTime('heartbeat_terakhir')->nullable()->after('waktu_submit');
        });
    }

    public function down(): void
    {
        Schema::table('ujian_attempt', function (Blueprint $table) {
            $table->dropColumn('heartbeat_terakhir');
        });

        Schema::dropIfExists('ujian_attempt_event');
    }
};
