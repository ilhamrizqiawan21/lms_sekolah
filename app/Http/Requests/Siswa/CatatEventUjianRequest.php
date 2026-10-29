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
        $attempt = $this->route('attempt');

        if (! $user || ! $user->isSiswa() || ! $attempt instanceof UjianAttempt) {
            return false;
        }

        return $user->siswa && (int) $attempt->siswa_id === (int) $user->siswa->id;
    }

    public function rules(): array
    {
        return [
            'jenis' => ['required', 'string', Rule::in(UjianAttemptEvent::JENIS_KLIEN)],
        ];
    }
}
