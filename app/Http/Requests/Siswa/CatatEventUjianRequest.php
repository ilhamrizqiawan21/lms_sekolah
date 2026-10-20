<?php

namespace App\Http\Requests\Siswa;

use App\Models\UjianAttempt;
use App\Models\UjianAttemptEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatatEventUjianRequest extends FormRequest
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

        return $siswa && (int) $attempt->siswa_id === (int) $siswa->id;
    }

    public function rules(): array
    {
        return [
            'jenis' => ['required', 'string', Rule::in(UjianAttemptEvent::JENIS_KLIEN)],
        ];
    }
}
