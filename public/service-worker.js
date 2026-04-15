const CACHE_NAME = 'citaclick-v5';
const STATIC_ASSETS = [
  '/',
  '/css/variables.css',
  '/css/reset.css',
  '/css/base.css',
  '/css/components.css',
  '/js/qrcode.min.js',
  '/js/app.js',
  '/js/router.js',
  '/js/api.js',
  '/js/auth.js',
  '/js/utils.js',
  '/js/i18n.js',
  '/js/calendar.js',
  '/locales/es.json',
  '/locales/en.json',
  '/manifest.json',
  '/offline.html',
  '/assets/icons/icon-192.svg',
  '/assets/icons/icon-512.svg',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS);
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  if (url.pathname.startsWith('/api/')) {
    event.respondWith(networkFirst(event.request));
    return;
  }

  event.respondWith(cacheFirst(event.request));
});

async function cacheFirst(request) {
  const cached = await caches.match(request);
  if (cached) return cached;

  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, response.clone());
    }
    return response;
  } catch (err) {
    return caches.match('/offline.html');
  }
}

async function networkFirst(request) {
  try {
    const response = await fetch(request);
    return response;
  } catch (err) {
    const cached = await caches.match(request);
    if (cached) return cached;
    return new Response(
      JSON.stringify({ success: false, message: 'Sin conexion' }),
      { headers: { 'Content-Type': 'application/json' } }
    );
  }
}

self.addEventListener('push', (event) => {
  const data = event.data ? event.data.json() : {};
  const title = data.title || 'CitaClick';
  const options = {
    body: data.body || '',
    icon: '/assets/icons/icon-192.svg',
    badge: '/assets/icons/icon-96.svg',
    vibrate: [100, 50, 100],
    data: { url: data.url || '/' },
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = event.notification.data.url || '/';
  event.waitUntil(
    self.clients.matchAll({ type: 'window' }).then((clients) => {
      const existing = clients.find((c) => c.url.includes(url));
      if (existing) return existing.focus();
      return self.clients.openWindow(url);
    })
  );
});
