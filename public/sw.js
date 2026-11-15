// Сервис-воркер приложения на главном экране.
// Данные (контракты, контакты) не кэшируются: всегда берутся с сервера.
// В кэше только страница «Нет связи» и иконка — их показываем без интернета.
const CACHE = 'tender-radar-v1';
const OFFLINE = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE)
            .then((cache) => cache.addAll([OFFLINE, '/icons/icon-192.png']))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') {
        return;
    }

    event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE)));
});
