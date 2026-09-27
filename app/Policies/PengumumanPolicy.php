<?php

namespace App\Policies;

use App\Models\KelasMapel;
use App\Models\Pengumuman;
use App\Models\User;

class PengumumanPolicy
{
    public function view(User $user, Pengumuman $pengumuman): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        if ($user->isKepalaSekolah()) {
            return in_array($pengumuman->target, ['semua', 'guru'], true) || (int) $pengumuman->created_by === (int) $user->id;
        }
        if ($user->isGuru()) {
            if (in_array($pengumuman->target, ['semua', 'guru'], true) || (int) $pengumuman->created_by === (int) $user->id) {
                return true;
            }

            return $pengumuman->target === 'kelas_mapel' && KelasMapel::whereIn('kelas_id', $pengumuman->targetKelasIds())->where('guru_id', $user->id)->exists();
        }

        return false;
    }

    public function manage(User $user, Pengumuman $pengumuman): bool
    {
        return $user->isAdmin() || ($user->isGuru() && (int) $pengumuman->created_by === (int) $user->id);
    }
}
