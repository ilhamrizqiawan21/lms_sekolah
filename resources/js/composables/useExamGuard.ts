import { onBeforeUnmount, onMounted, ref } from 'vue';
import { csrfHeaders } from '../utils/csrf';

export type ExamEventType = 'tab_hidden' | 'window_blur' | 'kembali' | 'salin' | 'klik_kanan' | 'fullscreen_exit' | 'tab_timeout';

/** Status kebijakan dari server (respons event dan heartbeat). */
interface ServerState {
    status?: string;
    pelanggaran?: number;
    maks_pelanggaran?: number | null;
}

interface ExamGuardOptions {
    /** false = ujian tanpa pengawasan: tidak ada listener, heartbeat, maupun layar penuh. */
    enabled: boolean;
    wajibFullscreen: boolean;
    eventUrl: string;
    heartbeatUrl: string;
    /** Pelanggaran yang sudah terhitung server saat halaman dibuka (agar tidak mulai dari nol setelah reload). */
    initialPelanggaran: number;
    maksPelanggaran: number | null;
    /** Dipanggil saat server melaporkan attempt tidak lagi berjalan (dikumpulkan paksa / waktu habis). */
    onAttemptEnded?: () => void;
}

const HEARTBEAT_INTERVAL_MS = 15_000;
const TAB_TIMEOUT_MS = 5_000;
const MAX_QUEUE = 50;

/**
 * Pengamat anti-cheat ujian CBT. Klien hanya melaporkan kejadian; keputusan pelanggaran dan
 * kumpul otomatis ada di server (CbtAntiCheatService) dan dikembalikan lewat respons.
 *
 * Satu "episode keluar" dilaporkan sekali: pindah tab memicu `blur` dan `visibilitychange`
 * bersamaan, jadi flag `away` mencegah hitungan ganda. Waktu dicap server, bukan klien.
 */
export function useExamGuard(options: ExamGuardOptions) {
    const pelanggaran = ref(options.initialPelanggaran);
    const maksPelanggaran = ref(options.maksPelanggaran);
    /** Pesan peringatan terakhir untuk siswa; null bila tidak ada atau sudah ditutup. */
    const peringatan = ref<string | null>(null);

    // Fullscreen wajib benar-benar didukung oleh browser. Jika tidak, halaman tetap dikunci
    // agar ujian tidak berjalan dalam mode yang lebih lemah dari kebijakan yang dipilih guru.
    const layarPenuhDidukung = typeof document !== 'undefined' && document.fullscreenEnabled === true;
    const layarPenuhWajib = options.enabled && options.wajibFullscreen;
    const sudahLayarPenuh = typeof document !== 'undefined' && document.fullscreenElement !== null;
    /** Menutupi soal sampai siswa masuk (atau kembali ke) layar penuh. */
    const perluLayarPenuh = ref(layarPenuhWajib && !sudahLayarPenuh);
    const layarPenuhError = ref<string | null>(null);

    let away = false;
    let wasFullscreen = sudahLayarPenuh;
    let awayTimeout: ReturnType<typeof setTimeout> | null = null;
    let flushing = false;
    let heartbeatTimer: ReturnType<typeof setInterval> | null = null;
    const queue: ExamEventType[] = [];

    function applyState(data: ServerState) {
        if (typeof data.pelanggaran === 'number') {
            if (data.pelanggaran > pelanggaran.value) {
                peringatan.value = pesanPeringatan(data.pelanggaran, data.maks_pelanggaran ?? maksPelanggaran.value);
            }
            pelanggaran.value = data.pelanggaran;
        }
        if (data.maks_pelanggaran !== undefined) {
            maksPelanggaran.value = data.maks_pelanggaran;
        }
        if (data.status && data.status !== 'sedang_mengerjakan') {
            options.onAttemptEnded?.();
        }
    }

    function pesanPeringatan(jumlah: number, maks: number | null): string {
        if (maks === null) {
            return `Keluar dari halaman ujian terdeteksi dan dicatat untuk guru. Total tercatat: ${jumlah}.`;
        }
        const sisa = maks - jumlah;
        const akibat = sisa <= 1 ? ' Satu pelanggaran lagi, jawaban Anda akan dikumpulkan otomatis.' : '';
        return `Peringatan: keluar dari halaman ujian terdeteksi. Pelanggaran ${jumlah} dari ${maks}.${akibat}`;
    }

    function tutupPeringatan() {
        peringatan.value = null;
    }

    function leave(trigger: 'tab_hidden' | 'window_blur') {
        if (away) return;
        away = true;
        peringatan.value = 'Peringatan: perpindahan tab atau jendela terdeteksi dan langsung dicatat sebagai pelanggaran.';
        report(trigger);
        awayTimeout = setTimeout(() => {
            if (away) {
                peringatan.value = 'Ujian diakhiri karena Anda berada di tab atau jendela lain selama lebih dari 5 detik.';
                report('tab_timeout');
            }
        }, TAB_TIMEOUT_MS);
    }

    function back() {
        if (!away) return;
        // Masih di luar: tab tersembunyi atau fokus belum kembali ke jendela ujian.
        if (document.visibilityState === 'hidden' || !document.hasFocus()) return;
        if (awayTimeout) {
            clearTimeout(awayTimeout);
            awayTimeout = null;
        }
        away = false;
        report('kembali');
    }

    /** Masukkan ke antrean lalu kirim berurutan; yang gagal dicoba lagi pada heartbeat berikutnya. */
    function report(jenis: ExamEventType) {
        if (!options.enabled || queue.length >= MAX_QUEUE) return;
        queue.push(jenis);
        void flush();
    }

    async function flush() {
        if (flushing) return;
        flushing = true;
        try {
            while (queue.length > 0) {
                const response = await fetch(options.eventUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: csrfHeaders(),
                    body: JSON.stringify({ jenis: queue[0] }),
                    keepalive: true,
                });
                // 4xx (mis. 422/403/429) tidak akan berhasil bila diulang; buang agar antrean tidak macet.
                if (response.ok || (response.status >= 400 && response.status < 500)) {
                    queue.shift();
                    if (response.ok) {
                        applyState((await response.json().catch(() => ({}))) as ServerState);
                    }
                } else {
                    break;
                }
            }
        } catch {
            // Jaringan putus: biarkan di antrean, dicoba lagi pada tick heartbeat.
        } finally {
            flushing = false;
        }
    }

    async function heartbeat() {
        void flush();
        try {
            const response = await fetch(options.heartbeatUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: csrfHeaders(),
                body: JSON.stringify({}),
            });
            if (!response.ok) return;
            applyState((await response.json()) as ServerState);
        } catch {
            // Heartbeat yang terlewat justru tercatat server sebagai heartbeat_gap.
        }
    }

    function onVisibilityChange() {
        if (document.visibilityState === 'hidden') {
            leave('tab_hidden');
        } else {
            back();
        }
    }

    const onBlur = () => leave('window_blur');
    const onFocus = () => back();

    function onFullscreenChange() {
        if (!layarPenuhWajib) return;
        const aktif = document.fullscreenElement !== null;
        if (aktif) {
            layarPenuhError.value = null;
        } else if (wasFullscreen) {
            // Keluar setelah sempat masuk = pelanggaran; masuk pertama kali bukan pelanggaran.
            report('fullscreen_exit');
        }
        wasFullscreen = aktif;
        perluLayarPenuh.value = !aktif;
    }

    /** Harus dipanggil dari klik siswa: browser menolak layar penuh tanpa gestur pengguna. */
    async function masukLayarPenuh() {
        if (!layarPenuhDidukung) {
            layarPenuhError.value = 'Browser atau perangkat ini tidak mendukung layar penuh. Gunakan Chrome/Edge di laptop atau Android untuk mengikuti ujian.';
            perluLayarPenuh.value = true;
            return;
        }

        try {
            await document.documentElement.requestFullscreen();
        } catch {
            // Gagal masuk layar penuh: tetap kunci halaman sampai siswa berhasil masuk fullscreen.
            layarPenuhError.value = 'Layar penuh tidak dapat diaktifkan. Izinkan fullscreen pada browser lalu coba lagi.';
            perluLayarPenuh.value = true;
        }
    }

    function cegahKeluarHalaman(event: BeforeUnloadEvent) {
        if (!options.enabled) return;
        event.preventDefault();
        event.returnValue = '';
    }

    onMounted(() => {
        if (!options.enabled) return;
        document.addEventListener('visibilitychange', onVisibilityChange);
        window.addEventListener('blur', onBlur);
        window.addEventListener('focus', onFocus);
        document.addEventListener('fullscreenchange', onFullscreenChange);
        window.addEventListener('beforeunload', cegahKeluarHalaman);
        heartbeatTimer = setInterval(() => void heartbeat(), HEARTBEAT_INTERVAL_MS);
        void heartbeat();
    });

    onBeforeUnmount(() => {
        document.removeEventListener('visibilitychange', onVisibilityChange);
        window.removeEventListener('blur', onBlur);
        window.removeEventListener('focus', onFocus);
        // Lepas listener dulu: keluar layar penuh karena ujian selesai bukan pelanggaran.
        document.removeEventListener('fullscreenchange', onFullscreenChange);
        window.removeEventListener('beforeunload', cegahKeluarHalaman);
        if (heartbeatTimer) clearInterval(heartbeatTimer);
        if (awayTimeout) clearTimeout(awayTimeout);
        if (document.fullscreenElement) void document.exitFullscreen().catch(() => {});
    });

    return {
        report,
        pelanggaran,
        maksPelanggaran,
        peringatan,
        tutupPeringatan,
        perluLayarPenuh,
        layarPenuhError,
        masukLayarPenuh,
    };
}
