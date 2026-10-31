<?php

namespace App\Http\Requests\Guru;

use Illuminate\Foundation\Http\FormRequest;

class ExportKurikulumPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isGuru() === true;
    }

    public function rules(): array
    {
        return [
            'kelas_mapel_id' => ['nullable', 'integer', 'exists:kelas_mapel,id'],
            'kelas_mapel_ids' => ['nullable', 'array', 'min:1', 'max:50'],
            'kelas_mapel_ids.*' => ['integer', 'distinct', 'exists:kelas_mapel,id'],
            'format' => ['nullable', 'in:docx,pdf,both'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->filled('kelas_mapel_id') && ! $this->filled('kelas_mapel_ids')) {
                $validator->errors()->add('kelas_mapel_ids', 'Pilih minimal satu kelas-mapel untuk diekspor.');
            }
        });
    }
}
