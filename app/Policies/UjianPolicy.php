<?php

namespace App\Policies;

use App\Models\Ujian;
use App\Models\User;

class UjianPolicy
{
    public function mengajar(User $user, Ujian $ujian): bool
    {
        $kelasMapel = $ujian->kelasMapel;

        return $user->isGuru()
            && $kelasMapel !== null
            && (int) $kelasMapel->guru_id === (int) $user->id
            && $kelasMapel->isAktif();
    }
}
