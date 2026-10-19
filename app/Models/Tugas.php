<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tugas extends Model
{
    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'kelas_mapel_id',
        'judul',
        'deskripsi',
        'batas_waktu',
        'kategori_nilai',
    ];

    protected $casts = [
        'batas_waktu' => 'datetime',
    ];

    public function kelasMapel(): BelongsTo
    {
        return $this->belongsTo(KelasMapel::class, 'kelas_mapel_id');
    }

    public function pengumpulan(): HasMany
    {
        return $this->hasMany(PengumpulanTugas::class, 'tugas_id');
    }
}
