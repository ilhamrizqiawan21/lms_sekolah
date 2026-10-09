<?php

namespace App\Http\Requests\Siswa;

use App\Models\UjianAttempt;
use Illuminate\Foundation\Http\FormRequest;

class SimpanJawabanUjianRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user || ! $user->isSiswa()) {
            return false;
        }

        $attempt = $this->route('attempt');
        if (! $attempt instanceof UjianAttempt) {
            return false;
        }

        $siswa = $user->siswa;
        if (! $siswa || (int) $attempt->siswa_id !== (int) $siswa->id) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'attempt_jawaban_id' => ['required', 'integer', 'exists:ujian_attempt_jawaban,id'],
            'jawaban_opsi_id' => ['nullable', 'integer', 'exists:soal_bank_opsi,id'],
            'ragu_ragu' => ['nullable', 'boolean'],
        ];
    }
}
