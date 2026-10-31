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
            'kelas_mapel_id' => ['required', 'integer', 'exists:kelas_mapel,id'],
        ];
    }
}
