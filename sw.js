const CACHE_NAME = "nextbomb-shell-v6";
const CORE_ASSETS = [
  "./offline.html",
  "./assets/css/style.css",
  "./assets/css/error-pages.css",
  "./assets/css/site-enhancements.css",
  "./assets/js/theme-init.js",
  "./assets/js/theme-toggle.js",
  "./assets/js/site-loader.js",
  "./assets/js/site-pwa.js",
  "./manifest.webmanifest",
  "./assets/img/pwa-icon-192.png",
  "./assets/img/pwa-icon-512.png",
  "./assets/img/pwa-icon-maskable.png"
];
const OPTIONAL_ASSETS = [
  "./",
  "./index.html",
  "./404.html",
  "./403.html",
  "./500.html",
  "./503.html",
  "./tools.html",
  "./simulation-dashboard.html",
  "./whatsapp-bomber.html",
  "./call-bomber/index.html",
  "./sms-bomber/index.html",
  "./protect-number/index.html",
  "./about-us/index.html",
  "./our-donations/index.html",
  "./contact-us/index.html",
  "./privacy-policy/index.html",
  "./terms-of-service/index.html",
  "./disclaimer/index.html"
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    (async () => {
      const cache = await caches.open(CACHE_NAME);

      await cache.addAll(CORE_ASSETS);

      await Promise.allSettled(
        OPTIONAL_ASSETS.map(async (url) => {
          try {
            await cache.add(url);
          } catch (error) {
            // Optional routes should not block offline support for the core shell.
          }
        })
      );

      await self.skipWaiting();
    })()
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }

          return Promise.resolve();
        })
      )
    ).then(async () => {
      if ("navigationPreload" in self.registration) {
        await self.registration.navigationPreload.enable();
      }

      await self.clients.claim();
    })
  );
});

self.addEventListener("fetch", (event) => {
  const { request } = event;

  if (request.method !== "GET") {
    return;
  }

  const requestUrl = new URL(request.url);

  if (requestUrl.origin !== self.location.origin) {
    return;
  }

  if (request.mode === "navigate") {
    event.respondWith(
      (async () => {
        try {
          const preloadResponse = await event.preloadResponse;
          if (preloadResponse) {
            return preloadResponse;
          }

          const response = await fetch(request);
          if (response && response.ok) {
            const responseClone = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, responseClone));
          }

          return response;
        } catch (error) {
          const cachedPage = await caches.match(request, { ignoreSearch: true });
          if (cachedPage) {
            return cachedPage;
          }

          const offlinePage = await caches.match("./offline.html");
          if (offlinePage) {
            return offlinePage;
          }

          return caches.match("./index.html");
        }
      })()
    );

    return;
  }

  event.respondWith(
    caches.match(request, { ignoreSearch: true }).then((cachedResponse) => {
      if (cachedResponse) {
        return cachedResponse;
      }

      return fetch(request)
        .then((response) => {
          if (!response || !response.ok || response.type !== "basic") {
            return response;
          }

          const responseClone = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, responseClone));
          return response;
        })
        .catch(() => caches.match("./offline.html"));
    })
  );
});
