/**
 * ページ内インタラクション用の JSON API ヘルパー。
 * 解答判定などレスポンスを画面内で使うリクエストは Inertia ではなく fetch で行う。
 */
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function requestJson<T>(
    method: 'POST' | 'PATCH',
    url: string,
    body: Record<string, unknown>,
): Promise<T> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        const data = await response.json().catch(() => ({}));

        throw new Error(data.message ?? `Request failed: ${response.status}`);
    }

    return response.json();
}

export async function postJson<T>(
    url: string,
    body: Record<string, unknown>,
): Promise<T> {
    return requestJson<T>('POST', url, body);
}

export async function patchJson<T>(
    url: string,
    body: Record<string, unknown>,
): Promise<T> {
    return requestJson<T>('PATCH', url, body);
}
