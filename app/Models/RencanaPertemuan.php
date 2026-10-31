<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RencanaPertemuan extends Model
{
    public const STATUS_DIRENCANAKAN = 'direncanakan';

    public const STATUS_TERLAKSANA = 'terlaksana';

    public const STATUS_DITUNDA = 'ditunda';

    public const STATUS_DIGESER = 'digeser';

    protected $table = 'rencana_pertemuan';

    protected $fillable = [
        'kurikulum_id',
        'kelas_mapel_id',
        'tujuan_pembelajaran_id',
        'pertemuan_ke',
        'tanggal_rencana',
        'jp',
        'status',
        'tanggal_realisasi',
        'catatan',
    ];

    protected $casts = [
        'tanggal_rencana' => 'date',
        'tanggal_realisasi' => 'date',
        'pertemuan_ke' => 'integer',
        'jp' => 'integer',
    ];

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class, 'kurikulum_id');
    }

    public function kelasMapel(): BelongsTo
    {
        return $this->belongsTo(KelasMapel::class, 'kelas_mapel_id');
    }

    public function tujuanPembelajaran(): BelongsTo
    {
        return $this->belongsTo(TujuanPembelajaran::class, 'tujuan_pembelajaran_id');
    }

    public function jurnalHarian(): HasOne
    {
        return $this->hasOne(JurnalHarian::class, 'rencana_pertemuan_id');
    }
}
