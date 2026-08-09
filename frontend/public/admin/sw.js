/* NeatMeet admin workspace PWA — caches admin shell and delivers owner push. */
const CACHE = 'neatmeet-admin-v1';
const SHELL = ['/admin-icons/icon-192.svg', '/admin-icons/icon-512.svg'];

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
  if (!url.pathname.startsWith('/admin') && !url.pathname.startsWith('/_next')) return;

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
        return new Response('You are offline. Reconnect to use the admin workspace.', {
          status: 503,
          headers: { 'Content-Type': 'text/plain' },
        });
      }),
  );
});

self.addEventListener('push', (event) => {
  let title = 'NeatMeet OS';
  let body = 'You have a platform update.';
  let url = '/admin/dashboard';
  let payload = {};
  try {
    const data = event.data ? event.data.json() : null;
    if (data?.title) title = data.title;
    if (data?.body) body = data.body;
    if (data?.url) url = data.url;
    if (data?.data && typeof data.data === 'object') payload = data.data;
    else if (data && typeof data === 'object') payload = data;
  } catch {
    if (event.data) body = event.data.text();
  }

  const isSos = Boolean(payload && (payload.type === 'staff_sos' || payload.sos || payload.require_ack));

  event.waitUntil(
    (async () => {
      if (isSos) {
        const clientsList = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const client of clientsList) {
          client.postMessage({ type: 'staff_sos', ...payload });
        }
      }
      await self.registration.showNotification(title, {
        body,
        icon: '/admin-icons/icon-192.svg',
        requireInteraction: isSos,
        vibrate: isSos ? [500, 200, 500, 200, 500] : undefined,
        tag: isSos ? `staff-sos-${payload.alert_id || 'active'}` : undefined,
        renotify: isSos,
        data: { url, ...payload },
      });
    })(),
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = event.notification.data?.url || '/admin/dashboard';
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
      for (const client of clients) {
        if ('focus' in client && client.url.includes('/admin')) {
          if (event.notification.data?.type === 'staff_sos' || event.notification.data?.sos) {
            client.postMessage({ type: 'staff_sos', ...(event.notification.data || {}) });
          }
          client.navigate(target);
          return client.focus();
        }
      }
      if (self.clients.openWindow) return self.clients.openWindow(target);
    }),
  );
});
