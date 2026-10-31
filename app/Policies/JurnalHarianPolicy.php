<?php

namespace App\Policies;

use App\Models\JurnalHarian;
use App\Models\User;

class JurnalHarianPolicy
{
    public function view(User $user, JurnalHarian $jurnalHarian): bool
    {
        return $user->isAdmin()
            || ($user->isGuru() && (int) $jurnalHarian->guru_id === (int) $user->id)
            || $user->isKepalaSekolah();
    }

    public function update(User $user, JurnalHarian $jurnalHarian): bool
    {
        return $user->isAdmin()
            || ($user->isGuru() && (int) $jurnalHarian->guru_id === (int) $user->id);
    }

    public function delete(User $user, JurnalHarian $jurnalHarian): bool
    {
        return $this->update($user, $jurnalHarian);
    }
}
