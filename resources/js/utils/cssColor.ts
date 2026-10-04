/** Nilai komputasi sebuah token CSS (Chart.js tidak memahami `var()`). */
export function cssVar(name: string): string {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || 'currentColor';
}

/** Tambahkan alpha ke warna `#rgb`/`#rrggbb`; format lain dikembalikan apa adanya. */
export function withAlpha(color: string, alpha: number): string {
    const hex = color.trim().match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i)?.[1];
    if (!hex) return color;
    const full = hex.length === 3 ? [...hex].map((c) => c + c).join('') : hex;
    const [r, g, b] = [0, 2, 4].map((i) => parseInt(full.slice(i, i + 2), 16));
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}
