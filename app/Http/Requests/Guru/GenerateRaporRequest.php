<?php

namespace App\Http\Requests\Guru;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateRaporRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isGuru() === true;
    }

    public function rules(): array
    {
        return [
            'semester' => ['nullable', Rule::in(['1', '2'])],
        ];
    }
}
