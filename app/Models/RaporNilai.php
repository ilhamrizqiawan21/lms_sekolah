<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RaporNilai extends Model
{
    protected $table = 'rapor_nilai';

    protected $fillable = [
        'rapor_siswa_id',
        'kelas_mapel_id',
        'mata_pelajaran',
        'nilai_akhir',
        'predikat',
        'deskripsi',
    ];

    protected $casts = [
        'nilai_akhir' => 'float',
    ];

    public function raporSiswa(): BelongsTo
    {
        return $this->belongsTo(RaporSiswa::class, 'rapor_siswa_id');
    }

    public function kelasMapel(): BelongsTo
    {
        return $this->belongsTo(KelasMapel::class, 'kelas_mapel_id');
    }
}
