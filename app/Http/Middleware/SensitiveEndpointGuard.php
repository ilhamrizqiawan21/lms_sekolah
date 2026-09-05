<?php

namespace App\Http\Middleware;

use App\Models\Siswa;
use App\Models\User;
use App\Support\RoleAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prevents legacy endpoints from exposing shared/default passwords.
 *
 * This middleware intentionally sits before the controller action so the
 * legacy implementation cannot execute while the route remains available
 * for backwards compatibility.
 */
class SensitiveEndpointGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('admin.users.export.excel')) {
            return $this->safeUserPasswordStatusExport($request, $next);
        }

        if ($request->routeIs('admin.users.reset-password')) {
            return $this->secureStaffResetPassword($request, $next);
        }

        if ($request->routeIs('admin.kelas-siswa.reset-password')) {
            return $this->secureStudentResetPassword($request, $next);
        }

        return $next($request);
    }

    private function secureStaffResetPassword(Request $request, Closure $next): Response
    {
        if ($response = $this->authorizeAdmin($request, $next)) {
            return $response;
        }

        /** @var User|null $user */
        $user = $request->route('user');

        abort_unless($user instanceof User && ! $user->isSiswa(), 404);

        $user->forceFill([
            'password' => Hash::make(User::DEFAULT_PASSWORD),
            'is_password_default' => true,
        ])->save();

        return back()->with(
            'success',
            "Password {$user->nama_lengkap} berhasil direset ke ".User::DEFAULT_PASSWORD.'.'
        );
    }

    private function secureStudentResetPassword(Request $request, Closure $next): Response
    {
        if ($response = $this->authorizeAdmin($request, $next)) {
            return $response;
        }

        /** @var Siswa|null $siswa */
        $siswa = $request->route('siswa');
        $siswa?->loadMissing('user');
        $user = $siswa?->user;

        abort_unless($siswa instanceof Siswa && $user instanceof User && $user->isSiswa(), 404);

        $user->forceFill([
            'password' => Hash::make(User::DEFAULT_PASSWORD),
            'is_password_default' => true,
        ])->save();

        return back()
            ->with('success', 'Password siswa berhasil direset.')
            ->with('student_password', [
                'title' => 'Password baru siswa',
                'name' => $user->nama_lengkap,
                'username' => $user->username,
                'password' => User::DEFAULT_PASSWORD,
            ]);
    }

    private function safeUserPasswordStatusExport(Request $request, Closure $next): Response
    {
        if ($response = $this->authorizeAdmin($request, $next)) {
            return $response;
        }

        $validated = $request->validate([
            'role_id' => ['nullable', 'integer', Rule::in(RoleAccess::staffRoleIds())],
            'search' => 'nullable|string|max:100',
        ]);

        $query = User::with('role')
            ->whereHas('role', fn ($q) => $q->whereIn('nama_role', RoleAccess::STAFF_MANAGED_BY_ADMIN));

        if (! empty($validated['role_id'])) {
            $query->where('role_id', $validated['role_id']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('nama_lengkap', 'like', "%{$search}%");
            });
        }

        $filename = 'status_password_guru_staf_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Username', 'Nama', 'Role', 'Status Password']);

            $query->orderBy('nama_lengkap')->chunk(500, function ($users) use ($output): void {
                foreach ($users as $user) {
                    fputcsv($output, [
                        $user->username,
                        $user->nama_lengkap,
                        $user->role?->nama_role ?? '-',
                        $user->is_password_default ? 'Masih default/sementara' : 'Sudah diubah',
                    ]);
                }
            });

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeAdmin(Request $request, Closure $next): ?Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        abort_unless($user->hasRole('admin'), 403, 'Anda tidak memiliki akses ke halaman ini.');

        return null;
    }
}
