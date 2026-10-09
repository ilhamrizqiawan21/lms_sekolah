<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UjianAttemptJawaban extends Model
{
    protected $table = 'ujian_attempt_jawaban';

    public $timestamps = false;

    protected $fillable = [
        'ujian_attempt_id',
        'ujian_soal_id',
        'soal_bank_opsi_id',
        'urutan_opsi_ids',
        'ragu_ragu',
        'is_benar',
        'poin_didapat',
        'dijawab_pada',
    ];

    protected $casts = [
        'urutan_opsi_ids' => 'array',
        'ragu_ragu' => 'boolean',
        'is_benar' => 'boolean',
        'poin_didapat' => 'float',
        'dijawab_pada' => 'datetime',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(UjianAttempt::class, 'ujian_attempt_id');
    }

    public function ujianSoal(): BelongsTo
    {
        return $this->belongsTo(UjianSoal::class, 'ujian_soal_id');
    }

    public function opsiTerpilih(): BelongsTo
    {
        return $this->belongsTo(SoalBankOpsi::class, 'soal_bank_opsi_id');
    }
}
