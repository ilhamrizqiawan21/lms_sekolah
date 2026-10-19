<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Support\RoleAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreWaliKelasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole(RoleAccess::ADMIN);
    }

    public function rules(): array
    {
        return [
            'kelas_id' => ['required', 'exists:kelas,id'],
            'guru_id' => ['required', 'exists:users,id'],
            'tahun_ajaran_id' => ['required', 'exists:tahun_ajaran,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $guruId = $this->input('guru_id');
            if (! $guruId) {
                return;
            }

            $isGuru = User::whereKey($guruId)
                ->whereHas('role', fn ($q) => $q->where('nama_role', RoleAccess::GURU))
                ->where('is_active', true)
                ->exists();

            if (! $isGuru) {
                $validator->errors()->add('guru_id', 'Guru wali kelas yang dipilih tidak aktif atau tidak valid.');
            }
        });
    }
}
