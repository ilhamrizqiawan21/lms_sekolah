<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class AccountSettingsController extends Controller
{
    public function edit()
    {
        $user = Auth::user()->loadMissing(['role', 'siswa.kelas']);
        $role = $user->role?->nama_role;

        return Inertia::render('Account/Pengaturan', [
            'profile' => [
                'username' => $user->username,
                'email' => $user->email ?: '-',
                'nama_lengkap' => $user->nama_lengkap,
                'nip_nis' => $user->nip_nis ?: '-',
                'jenis_kelamin' => $this->genderLabel($user->jenis_kelamin),
                'role' => $role,
                'role_label' => $this->roleLabel($role),
                'foto_url' => $user->foto ? Storage::disk('public')->url($user->foto) : null,
                'is_active' => (bool) $user->is_active,
                'is_password_default' => (bool) $user->is_password_default,
                'created_at' => $user->created_at ? (string) $user->created_at : '-',
                'siswa' => $user->siswa ? [
                    'nis' => $user->siswa->nis ?: '-',
                    'kelas' => trim(($user->siswa->kelas?->tingkat ?? '') . ' ' . ($user->siswa->kelas?->nama_kelas ?? '')) ?: '-',
                    'angkatan' => $user->siswa->angkatan ?: '-',
                    'status' => $user->siswa->status ?: '-',
                    'tinggal_kelas' => (bool) $user->siswa->tinggal_kelas,
                ] : null,
            ],
            'updateUrl' => $this->updateRoute($role),
            'avatarUpdateUrl' => $this->avatarUpdateRoute($role),
            'avatarDeleteUrl' => $this->avatarDeleteRoute($role),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required',
                'confirmed',
                Password::min(10)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
            'is_password_default' => false,
        ]);

        return back()->with('success', 'Password berhasil diperbarui.');
    }

    public function uploadAvatar(Request $request)
    {
        $validated = $request->validate([
            'foto' => 'required|file|extensions:jpg,jpeg,png,webp|max:2048',
        ], [
            'foto.extensions' => 'Foto harus berupa file .jpg, .jpeg, .png, atau .webp.',
            'foto.max' => 'Ukuran foto maksimal 2MB.',
        ]);

        $user = $request->user();
        $oldPath = $user->foto;
        $path = $validated['foto']->store('avatars/' . $user->id, 'public');

        $user->update(['foto' => $path]);

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        return back()->with('success', 'Foto profil berhasil diperbarui.');
    }

    public function deleteAvatar(Request $request)
    {
        $user = $request->user();

        if ($user->foto) {
            Storage::disk('public')->delete($user->foto);
            $user->update(['foto' => null]);
        }

        return back()->with('success', 'Foto profil berhasil dihapus.');
    }

    private function updateRoute(?string $role): string
    {
        return match ($role) {
            'admin' => route('admin.pengaturan-akun.update'),
            'guru' => route('guru.pengaturan.update'),
            'siswa' => route('siswa.pengaturan.update'),
            'kepala_sekolah' => route('kepsek.pengaturan.update'),
            default => url()->current(),
        };
    }

    private function avatarUpdateRoute(?string $role): string
    {
        return match ($role) {
            'admin' => route('admin.pengaturan-akun.foto'),
            'guru' => route('guru.pengaturan.foto'),
            'siswa' => route('siswa.pengaturan.foto'),
            'kepala_sekolah' => route('kepsek.pengaturan.foto'),
            default => url()->current(),
        };
    }

    private function avatarDeleteRoute(?string $role): string
    {
        return match ($role) {
            'admin' => route('admin.pengaturan-akun.foto.delete'),
            'guru' => route('guru.pengaturan.foto.delete'),
            'siswa' => route('siswa.pengaturan.foto.delete'),
            'kepala_sekolah' => route('kepsek.pengaturan.foto.delete'),
            default => url()->current(),
        };
    }

    private function roleLabel(?string $role): string
    {
        return match ($role) {
            'admin' => 'Admin',
            'guru' => 'Guru',
            'siswa' => 'Siswa',
            'kepala_sekolah' => 'Kepala Sekolah',
            default => $role ?: '-',
        };
    }

    private function genderLabel(?string $gender): string
    {
        return match ($gender) {
            'L', 'l', 'laki-laki', 'Laki-laki', 'Laki-Laki' => 'Laki-laki',
            'P', 'p', 'perempuan', 'Perempuan' => 'Perempuan',
            default => $gender ?: '-',
        };
    }
}
