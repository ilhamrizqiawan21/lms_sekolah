<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UjianAttempt extends Model
{
    protected $table = 'ujian_attempt';

    public $timestamps = false;

    public const STATUS_BELUM_MULAI = 'belum_mulai';

    public const STATUS_SEDANG_MENGERJAKAN = 'sedang_mengerjakan';

    public const STATUS_SELESAI = 'selesai';

    public const STATUS_WAKTU_HABIS = 'waktu_habis';

    public const STATUS_TERKUNCI = [
        self::STATUS_SELESAI,
        self::STATUS_WAKTU_HABIS,
    ];

    public const ALASAN_PELANGGARAN = 'pelanggaran';

    protected $fillable = [
        'ujian_id',
        'siswa_id',
        'status',
        'seed',
        'urutan_soal_ids',
        'waktu_mulai',
        'batas_waktu',
        'waktu_submit',
        'alasan_selesai',
        'heartbeat_terakhir',
        'skor_total',
        'skor_maksimal',
        'tab_switch_log',
        'tab_switch_count',
    ];

    protected $casts = [
        'seed' => 'integer',
        'urutan_soal_ids' => 'array',
        'tab_switch_log' => 'array',
        'tab_switch_count' => 'integer',
        'waktu_mulai' => 'datetime',
        'batas_waktu' => 'datetime',
        'waktu_submit' => 'datetime',
        'heartbeat_terakhir' => 'datetime',
        'skor_total' => 'float',
        'skor_maksimal' => 'float',
    ];

    public function ujian(): BelongsTo
    {
        return $this->belongsTo(Ujian::class, 'ujian_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function jawaban(): HasMany
    {
        return $this->hasMany(UjianAttemptJawaban::class, 'ujian_attempt_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(UjianAttemptEvent::class, 'ujian_attempt_id');
    }
}
