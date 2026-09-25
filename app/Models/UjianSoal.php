<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UjianSoal extends Model
{
    protected $table = 'ujian_soal';

    public $timestamps = false;

    protected $fillable = [
        'ujian_id',
        'soal_bank_id',
        'poin',
        'urutan',
    ];

    protected $casts = [
        'poin' => 'float',
        'urutan' => 'integer',
    ];

    public function ujian(): BelongsTo
    {
        return $this->belongsTo(Ujian::class, 'ujian_id');
    }

    public function soalBank(): BelongsTo
    {
        return $this->belongsTo(SoalBank::class, 'soal_bank_id');
    }
}
