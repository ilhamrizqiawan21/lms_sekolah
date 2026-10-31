<?php

namespace App\Http\Requests\Guru;

use App\Models\SoalBank;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSoalBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isGuru() ?? false;
    }

    public function rules(): array
    {
        $tipeSoal = $this->input('tipe_soal', SoalBank::TYPE_PILIHAN_GANDA);
        $requiresOptions = in_array($tipeSoal, [SoalBank::TYPE_PILIHAN_GANDA, SoalBank::TYPE_BENAR_SALAH, SoalBank::TYPE_MENJODOHKAN], true);

        return [
            'pertanyaan' => ['required', 'string'],
            'pembahasan' => ['nullable', 'string'],
            'mapel_id' => ['nullable', 'integer', 'exists:mata_pelajaran,id'],
            'tipe_soal' => ['sometimes', 'string', Rule::in(SoalBank::TYPES)],
            'level_kognitif' => ['nullable', 'string', Rule::in(SoalBank::COGNITIVE_LEVELS)],
            'topik' => ['nullable', 'string', 'max:100'],
            'kesulitan' => ['required', 'in:mudah,sedang,sulit'],
            'kategori_nilai' => ['nullable', 'in:NH,STS,SAS,SAT'],
            'opsi' => array_values(array_filter([
                Rule::requiredIf($requiresOptions),
                'array',
                $requiresOptions ? 'min:2' : null,
                'max:5',
            ])),
            'opsi.*.teks_opsi' => ['required_with:opsi', 'string'],
            'opsi.*.pasangan_teks' => ['nullable', 'string', 'required_if:tipe_soal,'.SoalBank::TYPE_MENJODOHKAN],
            'opsi.*.is_benar' => ['required_with:opsi', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $opsi = $this->input('opsi', []);
            if (! is_array($opsi)) {
                return;
            }

            $tipeSoal = $this->input('tipe_soal', SoalBank::TYPE_PILIHAN_GANDA);
            if ($tipeSoal === SoalBank::TYPE_BENAR_SALAH && count($opsi) !== 2) {
                $validator->errors()->add('opsi', 'Soal benar-salah harus memiliki tepat dua opsi.');
            }

            if ($tipeSoal === SoalBank::TYPE_MENJODOHKAN) {
                $pertanyaanKiri = collect($opsi)->pluck('teks_opsi')->map(fn ($item) => trim((string) $item));
                $pasangan = collect($opsi)->pluck('pasangan_teks')->map(fn ($item) => trim((string) $item));
                if ($pertanyaanKiri->contains('')) {
                    $validator->errors()->add('opsi', 'Setiap subsoal menjodohkan harus memiliki pertanyaan di sisi kiri.');
                }
                if ($pertanyaanKiri->filter()->duplicates()->isNotEmpty()) {
                    $validator->errors()->add('opsi', 'Pertanyaan sisi kiri pada subsoal menjodohkan tidak boleh sama.');
                }
                if ($pasangan->contains('')) {
                    $validator->errors()->add('opsi', 'Setiap subsoal menjodohkan harus memiliki kunci pasangan di sisi kanan.');
                }
                if ($pasangan->filter()->duplicates()->isNotEmpty()) {
                    $validator->errors()->add('opsi', 'Kunci pasangan di sisi kanan tidak boleh sama.');
                }
            }

            if (! in_array($tipeSoal, [SoalBank::TYPE_PILIHAN_GANDA, SoalBank::TYPE_BENAR_SALAH], true)) {
                return;
            }

            $benarCount = collect($opsi)->filter(fn ($item) => filter_var($item['is_benar'] ?? false, FILTER_VALIDATE_BOOLEAN))->count();

            if ($benarCount !== 1) {
                $validator->errors()->add('opsi', 'Tepat satu opsi jawaban harus ditandai sebagai kunci jawaban yang benar.');
            }
        });
    }
}
