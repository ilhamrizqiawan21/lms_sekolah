<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SoalBank extends Model
{
    protected $table = 'soal_bank';

    public const KESULITAN_MUDAH = 'mudah';

    public const KESULITAN_SEDANG = 'sedang';

    public const KESULITAN_SULIT = 'sulit';

    protected $fillable = [
        'guru_id',
        'mapel_id',
        'pertanyaan',
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
