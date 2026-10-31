<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KurikulumDokumen extends Model
{
    protected $table = 'kurikulum_dokumen';

    public const JENIS_ALOKASI_WAKTU = 'alokasi_waktu';

    public const JENIS_PROTA = 'prota';

    public const JENIS_PROMES = 'promes';

    public const JENIS_KKTP = 'kktp';

    public const JENIS_MODUL_AJAR = 'modul_ajar';

    public const JENIS = [
        self::JENIS_ALOKASI_WAKTU,
        self::JENIS_PROTA,
        self::JENIS_PROMES,
        self::JENIS_KKTP,
        self::JENIS_MODUL_AJAR,
    ];

    protected $fillable = [
        'kurikulum_id',
        'kelas_mapel_id',
        'jenis',
        'semester',
        'bab_key',
        'judul',
        'isi',
        'status',
    ];

    protected $casts = [
        'kurikulum_id' => 'integer',
        'kelas_mapel_id' => 'integer',
        'isi' => 'array',
    ];

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class, 'kurikulum_id');
    }

    public function kelasMapel(): BelongsTo
    {
        return $this->belongsTo(KelasMapel::class, 'kelas_mapel_id');
    }
}
