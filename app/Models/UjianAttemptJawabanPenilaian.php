<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UjianAttemptJawabanPenilaian extends Model
{
    protected $table = 'ujian_attempt_jawaban_penilaian';

    public $timestamps = false;

    protected $fillable = [
        'ujian_attempt_jawaban_id',
        'dinilai_oleh',
        'poin_sebelumnya',
        'poin_diberikan',
        'catatan',
        'created_at',
    ];

    protected $casts = [
        'poin_sebelumnya' => 'float',
        'poin_diberikan' => 'float',
        'created_at' => 'datetime',
    ];

    public function jawaban(): BelongsTo
    {
        return $this->belongsTo(UjianAttemptJawaban::class, 'ujian_attempt_jawaban_id');
    }

    public function penilai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dinilai_oleh');
    }
}
