<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Absensi extends Model
{
    protected $table = 'absensi';

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d';

    protected $fillable = [
        'siswa_id',
        'kelas_mapel_id',
        'tanggal',
        'status',
        'keterangan',
        'is_daring',
        'kelas_daring_id',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'is_daring' => 'boolean',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function kelasMapel(): BelongsTo
    {
        return $this->belongsTo(KelasMapel::class, 'kelas_mapel_id');
    }

    public function kelasDaring(): BelongsTo
    {
        return $this->belongsTo(KelasDaring::class, 'kelas_daring_id');
    }
}
