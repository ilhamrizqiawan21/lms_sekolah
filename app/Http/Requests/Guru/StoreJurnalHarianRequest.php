<?php

namespace App\Http\Requests\Guru;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJurnalHarianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isGuru() || $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'kelas_mapel_id' => ['required', 'integer', 'exists:kelas_mapel,id'],
            'rencana_pertemuan_id' => ['nullable', 'integer', 'exists:rencana_pertemuan,id'],
            'tanggal' => ['required', 'date'],
            'pertemuan_ke' => ['required', 'integer', 'min:1', 'max:10000'],
            'jp' => ['required', 'integer', 'min:1', 'max:40'],
            'materi_pokok' => ['nullable', 'string', 'max:20000'],
            'tujuan_pembelajaran' => ['nullable', 'string', 'max:20000'],
            'kegiatan_pendahuluan' => ['nullable', 'string', 'max:20000'],
            'kegiatan_inti' => ['nullable', 'string', 'max:20000'],
            'kegiatan_penutup' => ['nullable', 'string', 'max:20000'],
            'hasil_pengamatan' => ['nullable', 'string', 'max:20000'],
            'refleksi' => ['nullable', 'string', 'max:20000'],
            'ket_kehadiran' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['draft', 'final'])],
        ];
    }
}
