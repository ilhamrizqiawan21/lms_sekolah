<?php

namespace App\Http\Requests\Guru;

use App\Models\Kurikulum;
use Illuminate\Foundation\Http\FormRequest;

class StoreCapaianPembelajaranRequest extends FormRequest
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
            'kode' => ['nullable', 'string', 'max:50'],
            'elemen' => ['nullable', 'string', 'max:150'],
            'deskripsi' => ['required', 'string', 'max:20000'],
            'urutan' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
