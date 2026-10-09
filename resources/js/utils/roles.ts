const ROLE_LABELS: Record<string, string> = {
    admin: 'Admin',
    guru: 'Guru',
    siswa: 'Siswa',
    kepala_sekolah: 'Kepala Sekolah',
};

/** Human label for a role slug, e.g. `kepala_sekolah` -> `Kepala Sekolah`. */
export function roleLabel(role?: string | null): string {
    if (!role) return '-';
    return ROLE_LABELS[role] ?? role.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}
