<?php

namespace App\Http\Requests\Guru;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKurikulumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isGuru();
    }

    public function rules(): array
    {
        return [
            'tahun_ajaran_id' => ['required', 'integer', 'exists:tahun_ajaran,id'],
            'mapel_id' => ['required', 'integer', 'exists:mata_pelajaran,id'],
            'semester' => ['required', Rule::in(['1', '2'])],
            'kelas_mapel_ids' => ['required', 'array', 'min:1', 'max:50'],
            'kelas_mapel_ids.*' => ['required', 'integer', 'distinct', 'exists:kelas_mapel,id'],
            'jenis_kurikulum' => ['required', 'string', 'max:30', Rule::in(['merdeka', 'kbc', 'kurtilas', 'lainnya'])],
            'fase' => ['nullable', 'string', 'max:20'],
            'nama' => ['required', 'string', 'max:150'],
            'deskripsi' => ['nullable', 'string', 'max:10000'],
            'status' => ['sometimes', Rule::in(['draft', 'diajukan', 'disetujui', 'aktif', 'arsip'])],
        ];
    }
}
