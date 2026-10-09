<?php

namespace App\Http\Requests\Guru;

use App\Models\SoalBank;
use App\Models\UjianAttempt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateUjianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isGuru() ?? false;
    }

    public function rules(): array
    {
        return [
            'judul' => ['required', 'string', 'max:200'],
            'deskripsi' => ['nullable', 'string'],
            'durasi_menit' => ['required', 'integer', 'min:1', 'max:600'],
            'kategori_nilai' => ['required', 'in:NH,STS,SAS,SAT'],
            'waktu_mulai' => ['nullable', 'date'],
            'waktu_selesai' => ['nullable', 'date', 'after_or_equal:waktu_mulai'],
            'acak_soal' => ['required', 'boolean'],
            'acak_opsi' => ['required', 'boolean'],
            'soal' => ['nullable', 'array'],
            'soal.*.soal_bank_id' => ['required_with:soal', 'integer', 'exists:soal_bank,id'],
            'soal.*.poin' => ['required_with:soal', 'numeric', 'min:0.01', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $ujian = $this->route('ujian');
            if (! $ujian) {
                return;
            }

            $hasActiveAttempts = UjianAttempt::query()
                ->where('ujian_id', $ujian->id)
                ->where('status', '!=', UjianAttempt::STATUS_BELUM_MULAI)
                ->exists();

            $soalInput = $this->input('soal');

            if ($hasActiveAttempts && $soalInput !== null) {
                // If attempt started, soal composition cannot be changed
            } elseif (is_array($soalInput) && ! empty($soalInput)) {
                $soalBankIds = collect($soalInput)->pluck('soal_bank_id')->filter()->unique()->all();

                $ownedCount = SoalBank::query()
                    ->where('guru_id', $this->user()->id)
                    ->whereIn('id', $soalBankIds)
                    ->count();

                if ($ownedCount !== count($soalBankIds)) {
                    $validator->errors()->add('soal', 'Beberapa butir soal yang dipilih tidak valid atau bukan milik Anda.');
                }
            }
        });
    }
}
