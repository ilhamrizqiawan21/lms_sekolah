<?php

namespace App\Services;

use App\Models\Kelas;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SiswaLifecycleService
{
    /**
     * Buat siswa baru beserta akun User terkait.
     *
     * @return array{user: User, password: string}
     *
     * @throws UniqueConstraintViolationException
     */
    public function create(array $validated): array
    {
        return DB::transaction(function () use ($validated) {
            $siswaRoleId = Role::where('nama_role', 'siswa')->value('id');
            $password = $this->generateInitialPassword();

            if (! $siswaRoleId) {
                throw new \RuntimeException('Role siswa belum tersedia.');
            }

            // Buat user dulu
            $user = User::create([
                'username' => $validated['nis'],
                'password' => Hash::make($password),
                'is_password_default' => true,
                'nama_lengkap' => $validated['nama_lengkap'],
                'nip_nis' => $validated['nis'],
                'role_id' => $siswaRoleId,
                'jenis_kelamin' => $validated['jenis_kelamin'],
                'is_active' => true,
            ]);

            // Buat siswa
            Siswa::create([
                'user_id' => $user->id,
                'nis' => $validated['nis'],
                'kelas_id' => $validated['kelas_id'],
                'status' => 'aktif',
            ]);

            return compact('user', 'password');
        });
    }

    /**
     * Luluskan seluruh siswa aktif pada suatu kelas (mengubah status siswa dan
     * menonaktifkan akun User terkait).
     */
    public function luluskanKelas(Kelas $kelas): int
    {
        return DB::transaction(function () use ($kelas) {
            $siswa = Siswa::where('kelas_id', $kelas->id)
                ->where('status', 'aktif')
                ->get(['id', 'user_id']);

            if ($siswa->isEmpty()) {
                return 0;
            }

            Siswa::whereIn('id', $siswa->pluck('id'))->update(['status' => 'lulus']);
            User::whereIn('id', $siswa->pluck('user_id')->filter())->update(['is_active' => false]);

            return $siswa->count();
        });
    }

    /**
     * Apakah siswa sudah memiliki riwayat akademik sehingga tidak boleh dihapus.
     */
    public function hasAcademicHistory(Siswa $siswa): bool
    {
        return $siswa->absensi()->exists()
            || $siswa->pengumpulanTugas()->exists()
            || $siswa->nilaiAkhir()->exists()
            || $siswa->sikapSosial()->exists()
            || $siswa->sikapSpiritual()->exists();
    }

    /**
     * Hapus siswa beserta akun User-nya.
     */
    public function delete(Siswa $siswa): void
    {
        DB::transaction(function () use ($siswa) {
            $siswa->delete();
            $siswa->user->delete();
        });
    }

    private function generateInitialPassword(): string
    {
        return User::DEFAULT_PASSWORD;
    }
}
