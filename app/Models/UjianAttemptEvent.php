<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UjianAttemptEvent extends Model
{
    protected $table = 'ujian_attempt_event';

    public $timestamps = false;

    public const JENIS_TAB_HIDDEN = 'tab_hidden';

    public const JENIS_WINDOW_BLUR = 'window_blur';

    public const JENIS_KEMBALI = 'kembali';

    public const JENIS_SALIN = 'salin';

    public const JENIS_KLIK_KANAN = 'klik_kanan';

    public const JENIS_FULLSCREEN_EXIT = 'fullscreen_exit';

    /** Diamati server (tidak ada heartbeat), bukan dilaporkan klien. */
    public const JENIS_HEARTBEAT_GAP = 'heartbeat_gap';

    /** Jenis yang boleh dikirim klien; heartbeat_gap sengaja tidak termasuk. */
    public const JENIS_KLIEN = [
        self::JENIS_TAB_HIDDEN,
        self::JENIS_WINDOW_BLUR,
        self::JENIS_KEMBALI,
        self::JENIS_SALIN,
        self::JENIS_KLIK_KANAN,
        self::JENIS_FULLSCREEN_EXIT,
    ];

    /** Jenis yang menaikkan ujian_attempt.tab_switch_count (siswa meninggalkan halaman ujian). */
    public const JENIS_KELUAR = [
        self::JENIS_TAB_HIDDEN,
        self::JENIS_WINDOW_BLUR,
    ];

    public const BATAS_EVENT_PER_ATTEMPT = 500;

    /**
     * Keluar dari halaman ujian selama ini atau kurang tidak dihitung pelanggaran
     * (tarik notifikasi, panggilan masuk, sentuhan tak sengaja di HP).
     */
    public const TOLERANSI_KELUAR_DETIK = 5;

    /** Browser menahan timer tab latar ke ±1x/menit, jadi jeda wajar < 90 detik tidak dianggap gap. */
    public const AMBANG_HEARTBEAT_GAP_DETIK = 90;

    protected $fillable = [
        'ujian_attempt_id',
        'jenis',
        'terjadi_pada',
        'durasi_detik',
    ];

    protected $casts = [
        'terjadi_pada' => 'datetime',
        'durasi_detik' => 'integer',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(UjianAttempt::class, 'ujian_attempt_id');
    }
}
