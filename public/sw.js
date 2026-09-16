// EventQR service worker. Deliberately tiny and hand-written so it can be
// debugged on event night. Caches: the app shell of pages a phone has visited
// (pass + scanner), and built assets. Never caches POSTs or /admin.
const CACHE = 'eventqr-v1';

self.addEventListener('install', (e) => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim())
));

self.addEventListener('fetch', (e) => {
    const url = new URL(e.request.url);
    if (e.request.method !== 'GET' || url.origin !== location.origin) return;
    if (url.pathname.startsWith('/admin') || url.pathname.startsWith('/livewire') || url.pathname === '/scan/bundle') return;

    const cacheable = url.pathname.startsWith('/build/') || url.pathname.startsWith('/pass/') || url.pathname === '/scan/app' || url.pathname === '/manifest.webmanifest';
    if (!cacheable) return;

    // Network first, fall back to cache. Assets are hashed so stale is impossible.
    e.respondWith(
        fetch(e.request).then((res) => {
            if (res.ok) caches.open(CACHE).then((c) => c.put(e.request, res.clone()));
            return res;
        }).catch(() => caches.match(e.request))
    );
});
