<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.require_password_change', false)
            && ! config('security.force_password_change', false)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user || ! $user->is_password_default) {
            return $next($request);
        }

        $role = $user->role?->nama_role;

        if (
            $request->routeIs('*.pengaturan-akun') ||
            $request->routeIs('*.pengaturan-akun.update') ||
            $request->routeIs('guru.pengaturan') ||
            $request->routeIs('guru.pengaturan.update') ||
            $request->routeIs('siswa.pengaturan') ||
            $request->routeIs('siswa.pengaturan.update') ||
            $request->routeIs('siswa.pengaturan.telepon') ||
            $request->routeIs('kepsek.pengaturan') ||
            $request->routeIs('kepsek.pengaturan.update') ||
            $request->routeIs('*.profil') ||
            $request->routeIs('*.profil.update') ||
            $request->routeIs('login') ||
            $request->routeIs('login.post') ||
            $request->routeIs('logout')
        ) {
            return $next($request);
        }

        $route = match ($role) {
            'admin' => 'admin.pengaturan-akun',
            'guru' => 'guru.pengaturan',
            'siswa' => 'siswa.pengaturan',
            'kepala_sekolah' => 'kepsek.pengaturan',
            default => null,
        };

        if ($route && app('router')->has($route)) {
            return redirect()->route($route)->with('warning', 'Untuk keamanan, ubah password default sebelum melanjutkan.');
        }

        return $next($request);
    }
}
