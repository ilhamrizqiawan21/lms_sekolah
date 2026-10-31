<?php

namespace App\Http\Requests\Guru;

use Illuminate\Foundation\Http\FormRequest;

class GradeUjianJawabanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isGuru() ?? false;
    }

    public function rules(): array
    {
        return [
            'poin_didapat' => ['required', 'numeric', 'min:0'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
