<?php

namespace App\Http\Requests\Guru;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSoalBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isGuru() ?? false;
    }

    public function rules(): array
    {
        return [
            'pertanyaan' => ['required', 'string'],
            'mapel_id' => ['nullable', 'integer', 'exists:mata_pelajaran,id'],
            'topik' => ['nullable', 'string', 'max:100'],
            'kesulitan' => ['required', 'in:mudah,sedang,sulit'],
            'kategori_nilai' => ['nullable', 'in:NH,STS,SAS,SAT'],
            'opsi' => ['required', 'array', 'min:2', 'max:5'],
            'opsi.*.teks_opsi' => ['required', 'string'],
            'opsi.*.is_benar' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $opsi = $this->input('opsi', []);
            if (! is_array($opsi)) {
                return;
            }

            $benarCount = collect($opsi)->filter(fn ($item) => filter_var($item['is_benar'] ?? false, FILTER_VALIDATE_BOOLEAN))->count();

            if ($benarCount !== 1) {
                $validator->errors()->add('opsi', 'Tepat satu opsi jawaban harus ditandai sebagai kunci jawaban yang benar.');
            }
        });
    }
}
