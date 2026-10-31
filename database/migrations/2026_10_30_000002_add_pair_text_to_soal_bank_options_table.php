<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soal_bank_opsi', function (Blueprint $table) {
            $table->text('pasangan_teks')->nullable()->after('teks_opsi');
        });
    }

    public function down(): void
    {
        Schema::table('soal_bank_opsi', function (Blueprint $table) {
            $table->dropColumn('pasangan_teks');
        });
    }
};
