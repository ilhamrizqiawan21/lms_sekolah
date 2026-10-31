<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kurikulum extends Model
{
    protected $table = 'kurikulum';

    protected $fillable = [
        'tahun_ajaran_id',
        'mapel_id',
        'guru_id',
        'semester',
        'jenis_kurikulum',
        'fase',
        'nama',
        'deskripsi',
        'status',
    ];

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id');
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class, 'mapel_id');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function capaianPembelajaran(): HasMany
    {
        return $this->hasMany(CapaianPembelajaran::class, 'kurikulum_id')->orderBy('urutan');
    }

    public function tujuanPembelajaran(): HasMany
    {
        return $this->hasMany(TujuanPembelajaran::class, 'kurikulum_id')->orderBy('urutan');
    }

    public function kelasMapel(): BelongsToMany
    {
        return $this->belongsToMany(KelasMapel::class, 'kurikulum_kelas_mapel', 'kurikulum_id', 'kelas_mapel_id')
            ->withTimestamps();
    }

    public function rencanaPertemuan(): HasMany
    {
        return $this->hasMany(RencanaPertemuan::class, 'kurikulum_id');
    }

    public function dokumen(): HasMany
    {
        return $this->hasMany(KurikulumDokumen::class, 'kurikulum_id')->latest('updated_at');
    }
}
