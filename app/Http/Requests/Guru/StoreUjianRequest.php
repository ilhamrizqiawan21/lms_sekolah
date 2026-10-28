<?php

namespace App\Http\Requests\Guru;

use App\Models\SoalBank;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreUjianRequest extends FormRequest
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
            'soal' => ['required', 'array', 'min:1'],
            'soal.*.soal_bank_id' => ['required', 'integer', 'exists:soal_bank,id'],
            'soal.*.poin' => ['required', 'numeric', 'min:0.01', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $soalInput = $this->input('soal', []);
            if (! is_array($soalInput) || empty($soalInput)) {
                return;
            }

            $soalBankIds = collect($soalInput)->pluck('soal_bank_id')->filter()->unique()->all();

            $ownedCount = SoalBank::query()
                ->where('guru_id', $this->user()->id)
                ->whereIn('id', $soalBankIds)
                ->count();

            if ($ownedCount !== count($soalBankIds)) {
                $validator->errors()->add('soal', 'Beberapa butir soal yang dipilih tidak valid atau bukan milik Anda.');
            }
        });
    }
}
