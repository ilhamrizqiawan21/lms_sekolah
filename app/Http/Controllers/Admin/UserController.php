<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportSiswaRequest;
use App\Http\Requests\Admin\StaffUserFilterRequest;
use App\Http\Requests\Admin\StoreStaffUserRequest;
use App\Http\Requests\Admin\UpdateStaffUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Reports\Exports\LegacyTableExcelWriter;
use App\Services\SiswaImportService;
use App\Services\SiswaTemplateService;
use App\Support\RoleAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Tampilkan daftar user.
     */
    public function index(StaffUserFilterRequest $request)
    {
        $query = User::with('role')
            ->whereHas('role', fn ($query) => $query->whereIn('nama_role', RoleAccess::STAFF_MANAGED_BY_ADMIN));

        // Filter role
        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('nama_lengkap', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
        $roles = $this->staffRoles();

        return Inertia::render('Admin/Users/Index', [
            'users' => [
                'data' => $users->getCollection()->map(fn (User $user) => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'nama_lengkap' => $user->nama_lengkap,
                    'email' => $user->email,
                    'role' => [
                        'id' => $user->role?->id,
                        'nama_role' => $user->role?->nama_role,
                    ],
                    'is_active' => (bool) $user->is_active,
                    'password_is_default' => (bool) $user->is_password_default,
                    'password_status' => $user->is_password_default
                        ? 'Masih default'
                        : 'Sudah diubah',
                ])->values(),
                'links' => $users->linkCollection(),
                'meta' => [
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                    'total' => $users->total(),
                ],
            ],
            'roles' => $roles->map(fn (Role $role) => [
                'id' => $role->id,
                'nama_role' => $role->nama_role,
            ])->values(),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'role_id' => $request->string('role_id')->toString(),
            ],
            'exportUrl' => route('admin.users.export.excel'),
        ]);
    }

    public function exportExcel(StaffUserFilterRequest $request, LegacyTableExcelWriter $excel)
    {
        $this->ensureAdmin();

        $query = User::with('role')
            ->whereHas('role', fn ($query) => $query->whereIn('nama_role', RoleAccess::STAFF_MANAGED_BY_ADMIN));

        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('nama_lengkap', 'like', "%{$search}%");
            });
        }

        $filename = 'status_password_guru_staf_'.date('Ymd_His').'.xlsx';

        $rows = $query->orderBy('nama_lengkap')->get()->values()->map(fn (User $user) => [
            $user->username,
            $user->nama_lengkap,
            $user->role?->nama_role ? str_replace('_', ' ', ucwords($user->role->nama_role, '_')) : '-',
            $user->is_password_default ? 'Masih default' : 'Sudah diubah',
        ]);

        return $excel->simpleTable(
            $filename,
            [1 => 22, 2 => 32, 3 => 18, 4 => 18],
            [
                [[school_setting('school_name', 'Nama Sekolah')], 'school', 24],
                [['STATUS PASSWORD GURU & STAF'], 'title', 24],
                [['Tanggal Export', now()->format('d/m/Y H:i')], 'meta', 18],
            ],
            ['Username', 'Nama', 'Role', 'Status Password'],
            $rows,
            'guru_staf_'
        );
    }

    /**
     * Form tambah user.
     */
    public function create()
    {
        return Inertia::render('Admin/Users/Form', [
            'user' => null,
            'roles' => $this->staffRoleProps(),
            'storeUrl' => route('admin.users.store'),
        ]);
    }

    /**
     * Simpan user baru.
     */
    public function store(StoreStaffUserRequest $request)
    {
        $this->ensureAdmin();
        $validated = $request->validated();

        $role = Role::findOrFail($validated['role_id']);
        $this->ensureStaffRole($role);

        $plainPassword = $request->filled('password') ? $validated['password'] : User::DEFAULT_PASSWORD;
        $validated['password'] = Hash::make($plainPassword);
        $validated['is_password_default'] = $plainPassword === User::DEFAULT_PASSWORD;
        $validated['is_active'] = $request->boolean('is_active');

        DB::transaction(fn () => User::create($validated));

        return redirect()->route('admin.users.index')
            ->with('success', 'Akun guru/staf berhasil ditambahkan.');
    }

    /**
     * Unduh template import siswa.
     */
    public function downloadSiswaTemplate(SiswaTemplateService $templateService)
    {
        $this->ensureAdmin();

        return response()
            ->download($templateService->createTemplateFile(), SiswaTemplateService::FILENAME)
            ->deleteFileAfterSend(true);
    }

    /**
     * Import banyak siswa dari file Excel.
     */
    public function importSiswa(ImportSiswaRequest $request, SiswaImportService $importService)
    {
        $this->ensureAdmin();

        $result = $importService->import($request->file('file_siswa')->getRealPath());

        if ($result['errors'] !== []) {
            return back()->with('import_errors', $result['errors']);
        }

        return back()->with('success', $result['imported'].' siswa berhasil diimport.');
    }

    /**
     * Form edit user.
     */
    public function edit(User $user)
    {
        $this->ensureNotSiswaUser($user);

        return Inertia::render('Admin/Users/Form', [
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'nama_lengkap' => $user->nama_lengkap,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'nip_nis' => $user->nip_nis,
                'jenis_kelamin' => $user->jenis_kelamin,
                'is_active' => (bool) $user->is_active,
                'update_url' => route('admin.users.update', $user),
            ],
            'roles' => $this->staffRoleProps(),
            'storeUrl' => null,
        ]);
    }

    /**
     * Update user.
     */
    public function update(UpdateStaffUserRequest $request, User $user)
    {
        $this->ensureAdmin();
        $this->ensureNotSiswaUser($user);
        $validated = $request->validated();

        $role = Role::findOrFail($validated['role_id']);
        $this->ensureStaffRole($role);

        if ((int) $user->id === (int) Auth::id()
            && (! $request->boolean('is_active') || (int) $validated['role_id'] !== (int) $user->role_id)) {
            throw ValidationException::withMessages([
                'role_id' => 'Anda tidak dapat menonaktifkan atau mengubah role akun sendiri.',
            ]);
        }

        if ((int) $validated['role_id'] !== (int) $user->role_id
            && $user->kelasMapel()->exists()) {
            throw ValidationException::withMessages([
                'role_id' => 'Role tidak dapat diubah karena user ini sudah memiliki data siswa atau penugasan mengajar. Buat akun baru agar riwayat data tetap aman.',
            ]);
        }

        if ($this->isLastActiveAdmin($user)
            && (! $request->boolean('is_active') || $role->nama_role !== 'admin')) {
            throw ValidationException::withMessages([
                'role_id' => 'Sistem harus memiliki setidaknya satu admin aktif.',
            ]);
        }

        if ($request->filled('password')) {
            $validated['password'] = Hash::make($request->password);
            $validated['is_password_default'] = $request->password === User::DEFAULT_PASSWORD;
        } else {
            unset($validated['password']);
        }

        $validated['is_active'] = $request->boolean('is_active');

        DB::transaction(fn () => $user->update($validated));

        return redirect()->route('admin.users.index')
            ->with('success', 'User berhasil diperbarui.');
    }

    /**
     * Hapus user.
     */
    public function destroy(User $user)
    {
        $this->ensureAdmin();
        $this->ensureNotSiswaUser($user);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'Anda tidak dapat menghapus akun sendiri.');
        }

        if ($this->isLastActiveAdmin($user)) {
            return back()->with('error', 'User tidak dapat dihapus karena merupakan admin aktif terakhir.');
        }

        if ($user->kelasMapel()->exists()) {
            return back()->with('error', 'User tidak dapat dihapus karena masih memiliki penugasan mengajar.');
        }

        DB::transaction(fn () => $user->delete());

        return redirect()->route('admin.users.index')
            ->with('success', 'User berhasil dihapus.');
    }

    /**
     * Toggle status aktif/nonaktif.
     */
    public function toggleActive(User $user)
    {
        $this->ensureAdmin();
        $this->ensureNotSiswaUser($user);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
        }

        if ($user->is_active && $this->isLastActiveAdmin($user)) {
            return back()->with('error', 'Sistem harus memiliki setidaknya satu admin aktif.');
        }

        DB::transaction(fn () => $user->update(['is_active' => ! $user->is_active]));

        return back()->with('success', 'Status user berhasil diubah.');
    }

    /**
     * Reset password guru/staf ke password default.
     */
    public function resetPassword(User $user)
    {
        $this->ensureAdmin();
        $this->ensureNotSiswaUser($user);

        DB::transaction(fn () => $user->update([
            'password' => Hash::make(User::DEFAULT_PASSWORD),
            'is_password_default' => true,
        ]));

        return back()->with('success', "Password {$user->nama_lengkap} berhasil direset ke ".User::DEFAULT_PASSWORD.'.');
    }

    private function isLastActiveAdmin(User $user): bool
    {
        return $user->is_active
            && $user->hasRole('admin')
            && User::where('is_active', true)
                ->whereHas('role', fn ($query) => $query->where('nama_role', 'admin'))
                ->count() <= 1;
    }

    private function staffRoles()
    {
        return RoleAccess::staffRoles();
    }

    private function staffRoleProps()
    {
        return $this->staffRoles()->map(fn (Role $role) => [
            'id' => $role->id,
            'nama_role' => $role->nama_role,
        ])->values();
    }

    private function ensureStaffRole(Role $role): void
    {
        if (! RoleAccess::isStaffRole($role)) {
            throw ValidationException::withMessages([
                'role_id' => 'Role ini tidak dapat dikelola dari menu guru/staf.',
            ]);
        }
    }

    private function ensureNotSiswaUser(User $user): void
    {
        if ($user->isSiswa()) {
            abort(404);
        }
    }

    private function ensureAdmin(): void
    {
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException;
        }

        abort_unless($user->hasRole(RoleAccess::ADMIN), 403, 'Anda tidak memiliki akses ke halaman ini.');
    }
}
