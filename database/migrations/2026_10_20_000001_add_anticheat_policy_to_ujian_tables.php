<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ujian', function (Blueprint $table) {
            // Ujian yang sudah ada tetap diawasi (hanya dicatat), sama seperti perilaku sebelum kebijakan per ujian.
            $table->boolean('anticheat_aktif')->default(true)->after('acak_opsi');
            // null = hanya catat dan peringatkan; angka = kumpul otomatis saat pelanggaran mencapainya.
            $table->unsignedTinyInteger('maks_pelanggaran')->nullable()->after('anticheat_aktif');
            $table->boolean('wajib_fullscreen')->default(false)->after('maks_pelanggaran');
        });

        Schema::table('ujian_attempt', function (Blueprint $table) {
            // Diisi hanya bila sistem mengumpulkan paksa selain karena waktu habis (mis. 'pelanggaran').
            $table->string('alasan_selesai', 30)->nullable()->after('waktu_submit');
        });
    }

    public function down(): void
    {
        Schema::table('ujian_attempt', function (Blueprint $table) {
            $table->dropColumn('alasan_selesai');
        });

        Schema::table('ujian', function (Blueprint $table) {
            $table->dropColumn(['anticheat_aktif', 'maks_pelanggaran', 'wajib_fullscreen']);
        });
    }
};
