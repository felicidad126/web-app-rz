/*
 * Service worker Dental PMS.
 *
 * Aturan (Planning v2 §4.2 & §5.9):
 * - Hanya aset statis (build, ikon, font, aset Filament) yang di-cache.
 * - Semua request lain (HTML, Livewire, API) selalu ke jaringan dan TIDAK PERNAH di-cache,
 *   karena bisa berisi data pasien (UU PDP).
 * - Jika offline saat membuka halaman, tampilkan /offline.html.
 */
const VERSION = 'v1';
const STATIC_CACHE = `dental-pms-static-${VERSION}`;
const OFFLINE_URL = '/offline.html';
const PRECACHE = [OFFLINE_URL, '/icons/icon-192.png', '/icons/icon.svg'];
const STATIC_PATHS = ['/build/', '/icons/', '/fonts/', '/css/filament/', '/js/filament/', '/favicon.ico'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== STATIC_CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

const isStaticAsset = (url) => url.origin === self.location.origin && STATIC_PATHS.some((path) => url.pathname.startsWith(path));

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (isStaticAsset(url)) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
                }

                return response;
            })),
        );

        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
    }
    // Request lain: biarkan browser menangani langsung (network-only, tanpa cache).
});
