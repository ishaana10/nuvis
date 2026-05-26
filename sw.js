const CACHE_NAME = 'nubuilder-v1';
const urlsToCache = [
  './',
  './index.php',
  './assets/css/nubuilder-next.css',
  './assets/js/nubuilder-next.js'
];

self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function(cache) {
      return Promise.all(
        urlsToCache.map(function(url) {
          return fetch(url, { cache: 'no-cache' }).then(function(response) {
            if (!response.ok) {
              throw new Error('Failed to fetch: ' + url + ' (' + response.status + ')');
            }
            return cache.put(url, response.clone());
          });
        })
      );
    }).catch(function(err) {
      console.error('Service worker install cache error:', err);
    })
  );
});

self.addEventListener('fetch', function(event) {
  event.respondWith(
    caches.match(event.request).then(function(response) {
      if (response) return response;
      return fetch(event.request);
    })
  );
});

self.addEventListener('push', function(event) {
  var data = {
    title: 'Notification',
    body: '',
    url: '/',
    icon: './assets/icon-192.png',
    badge: './assets/icon-192.png'
  };

  if (event.data) {
    try {
      var payload = event.data.json();
      data.title = payload.title || data.title;
      data.body = payload.body || data.body;
      data.url = payload.url || data.url;
    } catch (e) {
      console.error('Push payload parse error:', e);
    }
  }

  event.waitUntil(
    self.registration.showNotification(data.title, {
      body: data.body,
      icon: data.icon,
      badge: data.badge,
      data: data.url
    })
  );
});

self.addEventListener('notificationclick', function(event) {
  event.notification.close();
  event.waitUntil(
    clients.openWindow(event.notification.data || './')
  );
});