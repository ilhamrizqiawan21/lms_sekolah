<?php

namespace App\Policies;

use App\Models\SoalBank;
use App\Models\User;

class SoalBankPolicy
{
    public function kelola(User $user, SoalBank $soalBank): bool
    {
        return $user->isGuru() && (int) $soalBank->guru_id === (int) $user->id;
    }
}
