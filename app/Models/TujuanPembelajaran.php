<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TujuanPembelajaran extends Model
{
    protected $table = 'tujuan_pembelajaran';

    protected $fillable = [
        'kurikulum_id',
        'capaian_pembelajaran_id',
        'kode',
        'urutan',
        'tujuan',
        'materi_pokok',
        'indikator_ketercapaian',
        'alokasi_jp',
        'estimasi_pertemuan',
        'bentuk_asesmen',
    ];

    protected $casts = [
        'urutan' => 'integer',
        'alokasi_jp' => 'integer',
        'estimasi_pertemuan' => 'integer',
    ];

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class, 'kurikulum_id');
    }

    public function capaianPembelajaran(): BelongsTo
    {
        return $this->belongsTo(CapaianPembelajaran::class, 'capaian_pembelajaran_id');
    }

    public function rencanaPertemuan(): HasMany
    {
        return $this->hasMany(RencanaPertemuan::class, 'tujuan_pembelajaran_id');
    }
}
