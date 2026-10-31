<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JurnalHarian extends Model
{
    protected $table = 'jurnal_harian';

    protected $fillable = [
        'guru_id',
        'kelas_mapel_id',
        'tahun_ajaran_id',
        'rencana_pertemuan_id',
        'tanggal',
        'pertemuan_ke',
        'jp',
        'materi_pokok',
        'tujuan_pembelajaran',
        'kegiatan_pendahuluan',
        'kegiatan_inti',
        'kegiatan_penutup',
        'hasil_pengamatan',
        'refleksi',
        'ket_kehadiran',
        'jumlah_siswa',
        'status',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'pertemuan_ke' => 'integer',
        'jp' => 'integer',
        'jumlah_siswa' => 'integer',
    ];

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function kelasMapel(): BelongsTo
    {
        return $this->belongsTo(KelasMapel::class, 'kelas_mapel_id');
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id');
    }

    public function rencanaPertemuan(): BelongsTo
    {
        return $this->belongsTo(RencanaPertemuan::class, 'rencana_pertemuan_id');
    }
}
