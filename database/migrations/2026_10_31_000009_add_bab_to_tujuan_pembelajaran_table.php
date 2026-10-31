<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tujuan_pembelajaran', 'bab')) {
            Schema::table('tujuan_pembelajaran', function (Blueprint $table) {
                $table->string('bab', 150)->nullable()->after('kode');
                $table->index(['kurikulum_id', 'bab']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tujuan_pembelajaran', 'bab')) {
            Schema::table('tujuan_pembelajaran', function (Blueprint $table) {
                $table->dropIndex(['kurikulum_id', 'bab']);
                $table->dropColumn('bab');
            });
        }
    }
};
