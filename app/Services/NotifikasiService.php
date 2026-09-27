<?php

namespace App\Services;

use App\Jobs\CreateClassNotifications;
use App\Models\Notifikasi;
use App\Models\Pengumuman;
use App\Models\User;

class NotifikasiService
{
    /**
     * Buat notifikasi untuk semua siswa dalam satu kelas_mapel.
     */
    public function notifikasiKelasMapel(int $kelasMapelId, string $tipe, string $judul, string $pesan, ?string $link = null): void
    {
        CreateClassNotifications::dispatch($kelasMapelId, $tipe, $judul, $pesan, $link)->afterResponse();
    }

    /**
     * Buat notifikasi untuk satu user.
     */
    public function notifikasiUser(int $userId, string $tipe, string $judul, string $pesan, ?string $link = null): Notifikasi
    {
        return Notifikasi::create([
            'user_id' => $userId,
            'tipe' => $tipe,
            'judul' => $judul,
            'pesan' => $pesan,
            'link' => $link,
        ]);
    }

    /**
     * Buat notifikasi "pengumuman baru" untuk seluruh penerima yang berhak
     * atas target pengumuman, selain pembuatnya sendiri.
     */
    public function notifyPengumumanRecipients(Pengumuman $pengumuman, int $excludeUserId): void
    {
        $query = User::query()->where('is_active', true)->where('id', '!=', $excludeUserId);
        $target = $pengumuman->target;
        if ($target === 'guru') {
            $query->whereHas('role', fn ($q) => $q->where('nama_role', 'guru'));
        } elseif ($target === 'siswa') {
            $query->whereHas('role', fn ($q) => $q->where('nama_role', 'siswa'));
        } elseif ($target === 'kelas_mapel') {
            $query->whereHas('role', fn ($q) => $q->where('nama_role', 'siswa'))->whereHas('siswa', fn ($q) => $q->whereIn('kelas_id', $pengumuman->targetKelasIds())->where('status', 'aktif'));
        } else {
            $query->whereHas('role', fn ($q) => $q->whereIn('nama_role', ['admin', 'guru', 'siswa', 'kepala_sekolah']));
        }
        foreach ($query->with('role')->get(['id', 'role_id']) as $user) {
            Notifikasi::create(['user_id' => $user->id, 'tipe' => 'pengumuman_baru', 'judul' => 'Pengumuman baru', 'pesan' => $pengumuman->judul, 'link' => $this->pengumumanLinkForUser($user, $pengumuman)]);
        }
    }

    private function pengumumanLinkForUser(User $user, Pengumuman $pengumuman): string
    {
        return match ($user->role?->nama_role) {
            'siswa' => route('siswa.pengumuman.show', $pengumuman), 'guru' => route('guru.pengumuman.show', $pengumuman),
            'kepala_sekolah' => route('kepsek.pengumuman.show', $pengumuman), default => route('admin.pengumuman.show', $pengumuman),
        };
    }
}
