// Appels JSON (session web + jeton CSRF du cookie XSRF-TOKEN) pour l'autosave et l'historique d'exercice.
function xsrf(): string {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
}

export async function json<T>(
    url: string,
    method = 'GET',
    body?: unknown,
): Promise<T> {
    const res = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': xsrf(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return res.status === 204 ? (undefined as T) : res.json();
}
