/* NeatMeet member PWA service worker — member routes only (not the whole site). */
const CACHE = 'neatmeet-member-v2';
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
  // Stay out of API, admin, booking, and marketing surfaces.
  if (!url.pathname.startsWith('/member')) return;

  event.respondWith(
    fetch(req)
      .then((res) => {
        const copy = res.clone();
        if (res.ok) {
          caches.open(CACHE).then((cache) => cache.put(req, copy)).catch(() => {});
        }
        return res;
      })
      .catch(async () => {
        const cached = await caches.match(req);
        if (cached) return cached;
        return new Response(
          '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Offline</title></head><body style="font-family:system-ui,sans-serif;padding:24px;color:#18181b;"><h1 style="font-size:1.25rem;">You are offline</h1><p>Reconnect to open the membership app, then try again.</p></body></html>',
          {
            status: 503,
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
          },
        );
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
