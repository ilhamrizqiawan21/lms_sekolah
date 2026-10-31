<?php

namespace App\Http\Requests\Guru;

use App\Models\JurnalHarian;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJurnalHarianRequest extends FormRequest
{
    public function authorize(): bool
    {
        $jurnalHarian = $this->route('jurnalHarian');

        return $jurnalHarian instanceof JurnalHarian
            && $this->user()?->can('update', $jurnalHarian);
    }

    public function rules(): array
    {
        return [
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
