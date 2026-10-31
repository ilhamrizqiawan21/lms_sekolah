<?php

namespace App\Policies;

use App\Models\Kurikulum;
use App\Models\User;

class KurikulumPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isGuru() || $user->isKepalaSekolah();
    }

    public function view(User $user, Kurikulum $kurikulum): bool
    {
        if ($user->isAdmin() || $user->isKepalaSekolah()) {
            return true;
        }

        return $user->isGuru()
            && (
                (int) $kurikulum->guru_id === (int) $user->id
                || $kurikulum->kelasMapel()->where('guru_id', $user->id)->exists()
            );
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isGuru();
    }

    public function update(User $user, Kurikulum $kurikulum): bool
    {
        return $user->isAdmin()
            || ($user->isGuru() && (int) $kurikulum->guru_id === (int) $user->id);
    }

    public function delete(User $user, Kurikulum $kurikulum): bool
    {
        return $this->update($user, $kurikulum);
    }

    public function attachKelasMapel(User $user, Kurikulum $kurikulum): bool
    {
        return $this->update($user, $kurikulum);
    }
}
