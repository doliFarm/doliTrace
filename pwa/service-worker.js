const CACHE_NAME = 'dolitrace-v3';
const ASSETS_TO_CACHE = [
    './',
    './index.html',
    './assets/css/style.css',
    './assets/js/app.js',
    './manifest.json',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
    'https://unpkg.com/vue@3/dist/vue.global.prod.js',
    'https://unpkg.com/html5-qrcode',
    'https://cdn-icons-png.flaticon.com/512/5556/5556468.png'
];

// 1. Installazione: Scarica i file
self.addEventListener('install', (evt) => {
    evt.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            console.log('[ServiceWorker] Caching assets');
            return cache.addAll(ASSETS_TO_CACHE);
        })
    );
});

// 2. Attivazione: Pulisce vecchie cache
self.addEventListener('activate', (evt) => {
    evt.waitUntil(
        caches.keys().then((keyList) => {
            return Promise.all(keyList.map((key) => {
                if (key !== CACHE_NAME) {
                    console.log('[ServiceWorker] Removing old cache', key);
                    return caches.delete(key);
                }
            }));
        })
    );
    self.clients.claim();
});

// 3. Fetch: Serve i file dalla cache se offline
self.addEventListener('fetch', (evt) => {
    // Strategia: Cache First, falling back to Network
    evt.respondWith(
        caches.match(evt.request).then((response) => {
            return response || fetch(evt.request);
        })
    );
});