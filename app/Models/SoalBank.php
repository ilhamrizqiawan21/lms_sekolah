<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SoalBank extends Model
{
    protected $table = 'soal_bank';

    public const TYPE_PILIHAN_GANDA = 'pilihan_ganda';

    public const TYPE_MENJODOHKAN = 'menjodohkan';

    public const TYPE_BENAR_SALAH = 'benar_salah';

    public const TYPE_URAIAN = 'uraian';

    public const TYPES = [
        self::TYPE_PILIHAN_GANDA,
        self::TYPE_MENJODOHKAN,
        self::TYPE_BENAR_SALAH,
        self::TYPE_URAIAN,
    ];

    public const COGNITIVE_LEVELS = ['C1', 'C2', 'C3', 'C4', 'C5', 'C6'];

    public const KESULITAN_MUDAH = 'mudah';

    public const KESULITAN_SEDANG = 'sedang';

    public const KESULITAN_SULIT = 'sulit';

    protected $fillable = [
        'guru_id',
        'mapel_id',
        'pertanyaan',
        'pembahasan',
        'tipe_soal',
        'level_kognitif',
        'topik',
        'kesulitan',
        'kategori_nilai',
    ];

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function mapel(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class, 'mapel_id');
    }

    public function opsi(): HasMany
    {
        return $this->hasMany(SoalBankOpsi::class, 'soal_bank_id');
    }
}
