/* NeatMeet member PWA service worker — caches app shell for offline open. */
const CACHE = 'neatmeet-member-v1';
const SHELL = ['/member-icons/icon-192.svg', '/member-icons/icon-512.svg'];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting()),
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))),
    ).then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  if (url.pathname.startsWith('/api/')) return;

  event.respondWith(
    fetch(req)
      .then((res) => {
        const copy = res.clone();
        if (res.ok && (url.pathname.startsWith('/member') || url.pathname.startsWith('/_next'))) {
          caches.open(CACHE).then((cache) => cache.put(req, copy)).catch(() => {});
        }
        return res;
      })
      .catch(async () => {
        const cached = await caches.match(req);
        if (cached) return cached;
        return new Response('You are offline. Reconnect to use the membership app.', {
          status: 503,
          headers: { 'Content-Type': 'text/plain' },
        });
      }),
  );
});

self.addEventListener('push', (event) => {
  let title = 'NeatMeet';
  let body = 'You have a salon update.';
  try {
    const data = event.data ? event.data.json() : null;
    if (data?.title) title = data.title;
    if (data?.body) body = data.body;
  } catch {
    if (event.data) body = event.data.text();
  }
  event.waitUntil(self.registration.showNotification(title, { body, icon: '/member-icons/icon-192.svg' }));
});
