<?php

namespace App\Http\Requests\Guru;

use App\Models\RaporSiswa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRaporRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rapor = $this->route('raporSiswa');

        return $rapor instanceof RaporSiswa && $this->user()?->can('kelola-wali-kelas', $rapor->waliKelas);
    }

    public function rules(): array
    {
        return [
            'catatan_wali_kelas' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', Rule::in(['draft', 'final'])],
            'nilai' => ['required', 'array'],
            'nilai.*.deskripsi' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
