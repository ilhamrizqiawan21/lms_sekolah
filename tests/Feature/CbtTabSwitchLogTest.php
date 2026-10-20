<?php

namespace Tests\Feature;

use App\Models\UjianAttempt;
use App\Models\UjianAttemptEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MembuatUjianCbt;
use Tests\TestCase;

/**
 * Event anti-cheat CBT (pindah tab/jendela, salin, klik kanan) dan heartbeat server.
 */
class CbtTabSwitchLogTest extends TestCase
{
    use MembuatUjianCbt;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_event_keluar_dicatat_dan_menaikkan_counter_sedangkan_event_lain_tidak(): void
    {
        $this->siapkanUjian();
        [$user, $attempt] = $this->mulaiAttempt();

        $this->kirimEvent($user, $attempt, 'tab_hidden')->assertOk();
        $this->kirimEvent($user, $attempt, 'window_blur')->assertOk();
        $this->kirimEvent($user, $attempt, 'kembali')->assertOk();
        $this->kirimEvent($user, $attempt, 'salin')->assertOk();
        $this->kirimEvent($user, $attempt, 'klik_kanan')->assertOk();

        $attempt->refresh();
        $this->assertSame(2, $attempt->tab_switch_count);
        $this->assertSame(
            ['tab_hidden', 'window_blur', 'kembali', 'salin', 'klik_kanan'],
            $attempt->events()->orderBy('id')->pluck('jenis')->all()
        );
        $this->assertNull($attempt->events()->first()->durasi_detik);
    }

    public function test_jenis_tidak_valid_dan_heartbeat_gap_dari_klien_ditolak(): void
    {
        $this->siapkanUjian();
        [$user, $attempt] = $this->mulaiAttempt();

        $this->kirimEvent($user, $attempt, 'heartbeat_gap')->assertUnprocessable();
        $this->kirimEvent($user, $attempt, 'asal_asalan')->assertUnprocessable();
        $this->actingAs($user)->postJson(route('siswa.ujian.event', $attempt->id), [])->assertUnprocessable();

        $this->assertSame(0, $attempt->events()->count());
        $this->assertSame(0, $attempt->refresh()->tab_switch_count);
    }

    public function test_event_ditolak_untuk_attempt_siswa_lain_dan_untuk_non_siswa(): void
    {
        $this->siapkanUjian();
        [, $attempt] = $this->mulaiAttempt('Siswa Pemilik');
        $penyusup = $this->makeUser('Siswa Lain', 'siswa');
        $this->createSiswa($penyusup, $this->kelas);

        $this->kirimEvent($penyusup, $attempt, 'tab_hidden')->assertForbidden();
        $this->kirimHeartbeat($penyusup, $attempt)->assertForbidden();

        $this->kirimEvent($this->guru, $attempt, 'tab_hidden')->assertForbidden();

        $this->assertSame(0, $attempt->events()->count());
        $this->assertSame(0, $attempt->refresh()->tab_switch_count);
    }

    public function test_event_setelah_submit_diabaikan(): void
    {
        $this->siapkanUjian();
        [$user, $attempt] = $this->mulaiAttempt();

        $this->kirimEvent($user, $attempt, 'tab_hidden')->assertOk();
        $this->actingAs($user)->post(route('siswa.ujian.submit', $attempt->id));

        $this->kirimEvent($user, $attempt, 'tab_hidden')->assertOk();

        $attempt->refresh();
        $this->assertSame(1, $attempt->tab_switch_count);
        $this->assertSame(1, $attempt->events()->count());
    }

    public function test_event_setelah_batas_waktu_diabaikan(): void
    {
        Carbon::setTestNow('2026-10-19 10:00:00');
        $this->siapkanUjian();
        [$user, $attempt] = $this->mulaiAttempt();

        Carbon::setTestNow('2026-10-19 10:31:00');
        $this->kirimEvent($user, $attempt, 'tab_hidden')->assertOk();

        $this->assertSame(0, $attempt->events()->count());
        $this->assertSame(0, $attempt->refresh()->tab_switch_count);
    }

    public function test_jumlah_event_per_attempt_dibatasi(): void
    {
        $this->siapkanUjian();
        [$user, $attempt] = $this->mulaiAttempt();

        $baris = [];
        for ($i = 0; $i < UjianAttemptEvent::BATAS_EVENT_PER_ATTEMPT; $i++) {
            $baris[] = ['ujian_attempt_id' => $attempt->id, 'jenis' => 'salin', 'terjadi_pada' => now()];
        }
        UjianAttemptEvent::insert($baris);

        $this->kirimEvent($user, $attempt, 'tab_hidden')->assertOk();

        $this->assertSame(UjianAttemptEvent::BATAS_EVENT_PER_ATTEMPT, $attempt->events()->count());
        $this->assertSame(0, $attempt->refresh()->tab_switch_count);
    }

    public function test_event_dan_heartbeat_dibatasi_throttle(): void
    {
        $this->siapkanUjian();
        [$user, $attempt] = $this->mulaiAttempt();

        for ($i = 0; $i < 60; $i++) {
            $this->kirimEvent($user, $attempt, 'kembali')->assertOk();
        }

        $this->kirimEvent($user, $attempt, 'kembali')->assertStatus(429);
        $this->kirimHeartbeat($user, $attempt)->assertStatus(429);
    }

    public function test_heartbeat_normal_tidak_membuat_gap_dan_mengembalikan_sisa_waktu(): void
    {
        Carbon::setTestNow('2026-10-19 10:00:00');
        $this->siapkanUjian();
        [$user, $attempt] = $this->mulaiAttempt();

        Carbon::setTestNow('2026-10-19 10:00:20');
        $this->kirimHeartbeat($user, $attempt)
            ->assertOk()
            ->assertJson(['status' => UjianAttempt::STATUS_SEDANG_MENGERJAKAN, 'sisa_detik' => 30 * 60 - 20]);

        $this->assertSame(0, $attempt->events()->count());
        $this->assertSame('2026-10-19 10:00:20', $attempt->refresh()->heartbeat_terakhir->format('Y-m-d H:i:s'));
    }

    public function test_heartbeat_dengan_jeda_panjang_mencatat_heartbeat_gap_oleh_server(): void
    {
        Carbon::setTestNow('2026-10-19 10:00:00');
        $this->siapkanUjian();
        [$user, $attempt] = $this->mulaiAttempt();

        Carbon::setTestNow('2026-10-19 10:02:00');
        $this->kirimHeartbeat($user, $attempt)->assertOk();

        $gap = $attempt->events()->sole();
        $this->assertSame(UjianAttemptEvent::JENIS_HEARTBEAT_GAP, $gap->jenis);
        $this->assertSame(120, $gap->durasi_detik);
        $this->assertSame('2026-10-19 10:02:00', $gap->terjadi_pada->format('Y-m-d H:i:s'));
        // heartbeat_gap adalah bukti, bukan "pindah tab": counter tidak berubah.
        $this->assertSame(0, $attempt->refresh()->tab_switch_count);

        // Heartbeat berikutnya yang tepat waktu tidak menambah gap.
        Carbon::setTestNow('2026-10-19 10:02:15');
        $this->kirimHeartbeat($user, $attempt)->assertOk();
        $this->assertSame(1, $attempt->events()->count());
    }

    public function test_guru_pengampu_melihat_events_berurutan_dan_guru_lain_ditolak(): void
    {
        Carbon::setTestNow('2026-10-19 10:00:00');
        $this->siapkanUjian();
        [$user, $attempt] = $this->mulaiAttempt();

        $this->kirimEvent($user, $attempt, 'tab_hidden')->assertOk();
        Carbon::setTestNow('2026-10-19 10:00:30');
        $this->kirimEvent($user, $attempt, 'kembali')->assertOk();

        $url = route('guru.ujian.attempt.show', [$this->ujian->kelas_mapel_id, $this->ujian->id, $attempt->id]);

        $this->actingAs($this->guru)->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Guru/Ujian/AttemptDetail')
                ->where('attempt.tab_switch_count', 1)
                ->where('attempt.events.0.jenis', 'tab_hidden')
                ->where('attempt.events.0.waktu', '19/10/2026 10:00:00')
                ->where('attempt.events.1.jenis', 'kembali')
                ->where('attempt.events.1.waktu', '19/10/2026 10:00:30')
            );

        $guruLain = $this->makeUser('Guru Lain', 'guru');
        $this->actingAs($guruLain)->get($url)->assertForbidden();
    }

    public function test_heartbeat_pada_attempt_selesai_tidak_menulis_apa_pun(): void
    {
        Carbon::setTestNow('2026-10-19 10:00:00');
        $this->siapkanUjian();
        [$user, $attempt] = $this->mulaiAttempt();
        $this->actingAs($user)->post(route('siswa.ujian.submit', $attempt->id));
        $terakhir = $attempt->refresh()->heartbeat_terakhir;

        Carbon::setTestNow('2026-10-19 10:10:00');
        $this->kirimHeartbeat($user, $attempt)
            ->assertOk()
            ->assertJson(['status' => UjianAttempt::STATUS_SELESAI, 'sisa_detik' => 0]);

        $this->assertSame(0, $attempt->events()->count());
        $this->assertEquals($terakhir, $attempt->refresh()->heartbeat_terakhir);
    }
}
