<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index request paths that are read frequently during normal LMS use.
     *
     * These are additive only: they do not transform or delete application
     * data. Deploy them during a low-traffic window because an index build
     * still consumes database resources on large tables.
     */
    public function up(): void
    {
        Schema::table('kelas_mapel', function (Blueprint $table) {
            $table->index(['guru_id', 'semester', 'tahun_ajaran_id'], 'kelas_mapel_guru_active_idx');
        });

        Schema::table('absensi', function (Blueprint $table) {
            $table->index('tanggal', 'absensi_tanggal_idx');
        });

        Schema::table('notifikasi', function (Blueprint $table) {
            $table->index(['user_id', 'is_read', 'created_at'], 'notifikasi_inbox_idx');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->index(['kelas_mapel_id', 'created_at'], 'chat_messages_course_created_idx');
        });

        Schema::table('pengumuman', function (Blueprint $table) {
            $table->index('created_at', 'pengumuman_created_at_idx');
        });

        Schema::table('log_login', function (Blueprint $table) {
            $table->index('login_time', 'log_login_login_time_idx');
        });
    }

    public function down(): void
    {
        Schema::table('log_login', function (Blueprint $table) {
            $table->dropIndex('log_login_login_time_idx');
        });

        Schema::table('pengumuman', function (Blueprint $table) {
            $table->dropIndex('pengumuman_created_at_idx');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex('chat_messages_course_created_idx');
        });

        Schema::table('notifikasi', function (Blueprint $table) {
            $table->dropIndex('notifikasi_inbox_idx');
        });

        Schema::table('absensi', function (Blueprint $table) {
            $table->dropIndex('absensi_tanggal_idx');
        });

        Schema::table('kelas_mapel', function (Blueprint $table) {
            $table->dropIndex('kelas_mapel_guru_active_idx');
        });
    }
};
