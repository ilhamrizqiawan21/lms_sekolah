<?php

namespace App\Http\Requests\Guru;

use App\Models\Kurikulum;
use App\Models\RencanaPertemuan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRencanaPertemuanRequest extends FormRequest
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
            'tanggal_rencana' => ['required', 'date'],
            'tujuan_pembelajaran_id' => [
                'required',
                'integer',
                Rule::exists('tujuan_pembelajaran', 'id')
                    ->where(fn ($query) => $query->where('kurikulum_id', $kurikulum?->id)),
            ],
            'jp' => ['required', 'integer', 'min:1', 'max:40'],
            'status' => ['required', Rule::in([
                RencanaPertemuan::STATUS_DIRENCANAKAN,
                RencanaPertemuan::STATUS_TERLAKSANA,
                RencanaPertemuan::STATUS_DITUNDA,
                RencanaPertemuan::STATUS_DIGESER,
            ])],
            'tanggal_realisasi' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
