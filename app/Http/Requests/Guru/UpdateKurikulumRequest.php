<?php

namespace App\Http\Requests\Guru;

use App\Models\Kurikulum;

class UpdateKurikulumRequest extends StoreKurikulumRequest
{
    public function authorize(): bool
    {
        $kurikulum = $this->route('kurikulum');

        return $kurikulum instanceof Kurikulum
            && $this->user()?->can('update', $kurikulum);
    }
}
