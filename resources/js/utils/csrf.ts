/** Header untuk `fetch` JSON ke route web Laravel (CSRF dari meta tag atau cookie XSRF-TOKEN). */
export function csrfHeaders(): Record<string, string> {
    const headers: Record<string, string> = {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    const metaToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    if (metaToken) {
        headers['X-CSRF-TOKEN'] = metaToken;
    }
    const xsrfMatch = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    if (xsrfMatch) {
        headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrfMatch[1]);
    }
    return headers;
}
