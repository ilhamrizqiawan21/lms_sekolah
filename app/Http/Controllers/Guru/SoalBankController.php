<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guru\StoreSoalBankRequest;
use App\Http\Requests\Guru\UpdateSoalBankRequest;
use App\Models\MataPelajaran;
use App\Models\SoalBank;
use App\Models\SoalBankOpsi;
use App\Models\UjianSoal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SoalBankController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();

        $query = SoalBank::query()
            ->with(['mapel', 'opsi'])
            ->where('guru_id', $user->id);

        if ($request->filled('mapel_id')) {
            $query->where('mapel_id', $request->input('mapel_id'));
        }

        if ($request->filled('topik')) {
            $query->where('topik', $request->input('topik'));
        }

        if ($request->filled('kesulitan')) {
            $query->where('kesulitan', $request->input('kesulitan'));
        }

        if ($request->filled('kategori_nilai')) {
            $query->where('kategori_nilai', $request->input('kategori_nilai'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($search) {
                $q->where('pertanyaan', 'like', $search)
                    ->orWhere('topik', 'like', $search);
            });
        }

        $soalList = $query->latest('id')->get();

        $mataPelajaran = MataPelajaran::query()
            ->orderBy('nama_mapel')
            ->get(['id', 'nama_mapel', 'kode']);

        $topics = SoalBank::query()
            ->where('guru_id', $user->id)
            ->whereNotNull('topik')
            ->distinct()
            ->pluck('topik')
            ->filter()
            ->values();

        return Inertia::render('Guru/SoalBank/Index', [
            'soalList' => $soalList,
            'mataPelajaran' => $mataPelajaran,
            'topics' => $topics,
            'filters' => [
                'mapel_id' => $request->input('mapel_id', ''),
                'topik' => $request->input('topik', ''),
                'kesulitan' => $request->input('kesulitan', ''),
                'kategori_nilai' => $request->input('kategori_nilai', ''),
                'search' => $request->input('search', ''),
            ],
        ]);
    }

    public function store(StoreSoalBankRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = Auth::user();

        DB::transaction(function () use ($validated, $user) {
            $soalBank = SoalBank::create([
                'guru_id' => $user->id,
                'mapel_id' => $validated['mapel_id'] ?? null,
                'pertanyaan' => $validated['pertanyaan'],
                'topik' => $validated['topik'] ?? null,
                'kesulitan' => $validated['kesulitan'],
                'kategori_nilai' => $validated['kategori_nilai'] ?? null,
            ]);

            foreach ($validated['opsi'] as $index => $opsiItem) {
                SoalBankOpsi::create([
                    'soal_bank_id' => $soalBank->id,
                    'teks_opsi' => $opsiItem['teks_opsi'],
                    'is_benar' => filter_var($opsiItem['is_benar'], FILTER_VALIDATE_BOOLEAN),
                    'urutan' => $index + 1,
                ]);
            }
        });

        return redirect()->back()->with('success', 'Butir soal berhasil ditambahkan ke Bank Soal.');
    }

    public function update(UpdateSoalBankRequest $request, SoalBank $soalBank): RedirectResponse
    {
        $this->authorize('kelola', $soalBank);

        $isDipakai = UjianSoal::where('soal_bank_id', $soalBank->id)->exists();
        if ($isDipakai) {
            return redirect()->back()->withErrors([
                'error' => 'Soal ini tidak dapat diedit karena sudah dipakai dalam satu atau lebih ujian CBT.',
            ]);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($soalBank, $validated) {
            $soalBank->update([
                'mapel_id' => $validated['mapel_id'] ?? null,
                'pertanyaan' => $validated['pertanyaan'],
                'topik' => $validated['topik'] ?? null,
                'kesulitan' => $validated['kesulitan'],
                'kategori_nilai' => $validated['kategori_nilai'] ?? null,
            ]);

            $soalBank->opsi()->delete();

            foreach ($validated['opsi'] as $index => $opsiItem) {
                SoalBankOpsi::create([
                    'soal_bank_id' => $soalBank->id,
                    'teks_opsi' => $opsiItem['teks_opsi'],
                    'is_benar' => filter_var($opsiItem['is_benar'], FILTER_VALIDATE_BOOLEAN),
                    'urutan' => $index + 1,
                ]);
            }
        });

        return redirect()->back()->with('success', 'Butir soal berhasil diperbarui.');
    }

    public function destroy(SoalBank $soalBank): RedirectResponse
    {
        $this->authorize('kelola', $soalBank);

        $isDipakai = UjianSoal::where('soal_bank_id', $soalBank->id)->exists();
        if ($isDipakai) {
            return redirect()->back()->withErrors([
                'error' => 'Soal ini tidak dapat dihapus karena sudah dipakai dalam satu atau lebih ujian CBT.',
            ]);
        }

        $soalBank->delete();

        return redirect()->back()->with('success', 'Soal berhasil dihapus dari Bank Soal.');
    }
}

