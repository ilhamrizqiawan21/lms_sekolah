<?php

namespace App\Http\Requests\Guru;

use App\Models\Kurikulum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTujuanPembelajaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        $kurikulum = $this->route('kurikulum');

        return $kurikulum instanceof Kurikulum
            && $this->user()?->can('update', $kurikulum);
    }

    public function rules(): array
    {
        $kurikulum = $this->route('kurikulum');

        return [
            'capaian_pembelajaran_id' => [
                'nullable',
                'integer',
                Rule::exists('capaian_pembelajaran', 'id')
                    ->where(fn ($query) => $query->where('kurikulum_id', $kurikulum?->id)),
            ],
            'kode' => ['nullable', 'string', 'max:50'],
            'bab' => ['nullable', 'string', 'max:150'],
            'urutan' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'tujuan' => ['required', 'string', 'max:20000'],
            'materi_pokok' => ['nullable', 'string', 'max:20000'],
            'indikator_ketercapaian' => ['nullable', 'string', 'max:20000'],
            'alokasi_jp' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'estimasi_pertemuan' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'bentuk_asesmen' => ['nullable', 'string', 'max:150'],
        ];
    }
}
