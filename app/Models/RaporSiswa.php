<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RaporSiswa extends Model
{
    protected $table = 'rapor_siswa';

    protected $fillable = [
        'wali_kelas_id',
        'siswa_id',
        'tahun_ajaran_id',
        'semester',
        'status',
        'catatan_wali_kelas',
        'sakit',
        'izin',
        'alpha',
    ];

    protected $casts = [
        'sakit' => 'integer',
        'izin' => 'integer',
        'alpha' => 'integer',
    ];

    public function waliKelas(): BelongsTo
    {
        return $this->belongsTo(WaliKelas::class, 'wali_kelas_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id');
    }

    public function nilai(): HasMany
    {
        return $this->hasMany(RaporNilai::class, 'rapor_siswa_id');
    }
}
