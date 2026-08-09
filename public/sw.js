const CACHE = 'kyuyo-shell-v2';
const SHELL = ['/', '/favicon.svg', '/apple-touch-icon.png', '/manifest.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(SHELL)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)))));
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;

    event.respondWith(
        fetch(event.request).catch(async () => {
            const cached = await caches.match(event.request);

            if (cached) return cached;

            // HTMLナビゲーションだけ静的シェルへ戻す。JS/CSS/APIへHTMLを返すと
            // MIMEエラーや誤った成功レスポンスになるため、通常の通信失敗を維持する。
            if (event.request.mode === 'navigate') {
                return caches.match('/');
            }

            return Response.error();
        }),
    );
});
