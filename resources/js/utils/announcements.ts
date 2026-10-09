const TARGET_LABELS: Record<string, string> = { semua: 'Semua pengguna', guru: 'Guru', siswa: 'Siswa', kelas_mapel: 'Kelas tertentu' };

/** Human label for an announcement target slug, e.g. `kelas_mapel` -> `Kelas tertentu`. */
export function targetLabel(target?: string | null): string {
    return target ? TARGET_LABELS[target] ?? target : '-';
}

function parse(value?: string | null): Date | null {
    const date = value ? new Date(value) : null;
    return date && !Number.isNaN(date.getTime()) ? date : null;
}

export function formatDate(value?: string | null): string {
    return parse(value)?.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) ?? '–';
}

export function formatDateTime(value?: string | null): string {
    return parse(value)?.toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) ?? '–';
}
