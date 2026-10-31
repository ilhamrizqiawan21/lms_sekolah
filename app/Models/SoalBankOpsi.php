<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SoalBankOpsi extends Model
{
    protected $table = 'soal_bank_opsi';

    public $timestamps = false;

    protected $fillable = [
        'soal_bank_id',
        'teks_opsi',
        'pasangan_teks',
        'is_benar',
        'urutan',
    ];

    protected $casts = [
        'is_benar' => 'boolean',
        'urutan' => 'integer',
    ];

    public function soalBank(): BelongsTo
    {
        return $this->belongsTo(SoalBank::class, 'soal_bank_id');
    }
}
