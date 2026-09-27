<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Services\SiswaProgressService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ProgressController extends Controller
{
    public function __construct(private readonly SiswaProgressService $siswaProgressService) {}

    public function index()
    {
        $user = Auth::user();
        $siswa = $user->siswa;

        if (! $siswa) {
            return redirect()->route('login')->with('error', 'Data siswa tidak ditemukan.');
        }

        return Inertia::render('Siswa/Progress', $this->siswaProgressService->buildProgress($user, $siswa));
    }
}
