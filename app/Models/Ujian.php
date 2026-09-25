<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ujian extends Model
{
    protected $table = 'ujian';

    protected $fillable = [
        'kelas_mapel_id',
        'judul',
        'deskripsi',
        'durasi_menit',
        'kategori_nilai',
        'waktu_mulai',
        'waktu_selesai',
        'acak_soal',
        'acak_opsi',
    ];

    protected $casts = [
        'durasi_menit' => 'integer',
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
        'acak_soal' => 'boolean',
        'acak_opsi' => 'boolean',
    ];

    public function kelasMapel(): BelongsTo
    {
        return $this->belongsTo(KelasMapel::class, 'kelas_mapel_id');
    }

    public function ujianSoal(): HasMany
    {
        return $this->hasMany(UjianSoal::class, 'ujian_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(UjianAttempt::class, 'ujian_id');
    }

    public function isBuka(): bool
    {
        $now = Carbon::now();

        if ($this->waktu_mulai && $now->lt($this->waktu_mulai)) {
            return false;
        }

        if ($this->waktu_selesai && $now->gt($this->waktu_selesai)) {
            return false;
        }

        return true;
    }
}
