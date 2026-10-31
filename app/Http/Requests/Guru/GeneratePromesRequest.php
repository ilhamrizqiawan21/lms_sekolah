<?php

namespace App\Http\Requests\Guru;

use App\Models\Kurikulum;
use Illuminate\Foundation\Http\FormRequest;

class GeneratePromesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $kurikulum = $this->route('kurikulum');

        return $kurikulum instanceof Kurikulum
            && $this->user()?->can('update', $kurikulum);
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'jp_per_pertemuan' => ['required', 'integer', 'min:1', 'max:40'],
            'kelas_mapel_ids' => ['required', 'array', 'min:1', 'max:50'],
            'kelas_mapel_ids.*' => ['required', 'integer', 'distinct', 'exists:kelas_mapel,id'],
            'replace' => ['sometimes', 'boolean'],
        ];
    }
}
