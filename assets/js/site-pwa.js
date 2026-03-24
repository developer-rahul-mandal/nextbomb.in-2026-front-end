(function () {
  var buttons = Array.prototype.slice.call(document.querySelectorAll(".site-install-button"));
  var script = document.currentScript || document.querySelector('script[src*="site-pwa.js"]');
  var siteRoot = script ? new URL("../../", script.src) : new URL("./", window.location.href);
  var offlinePageUrl = new URL("offline.html", siteRoot);
  var deferredPrompt = null;

  function isStandalone() {
    var standaloneMedia = window.matchMedia && window.matchMedia("(display-mode: standalone)");
    return Boolean((standaloneMedia && standaloneMedia.matches) || window.navigator.standalone === true);
  }

  function setButtonVisibility(visible) {
    buttons.forEach(function (button) {
      button.hidden = !visible;
      button.setAttribute("aria-hidden", String(!visible));
    });
  }

  function hideInstallButtons() {
    setButtonVisibility(false);
  }

  function showInstallButtons() {
    if (isStandalone()) {
      hideInstallButtons();
      return;
    }

    setButtonVisibility(true);
  }

  function handleInstallClick() {
    if (!deferredPrompt) {
      return;
    }

    deferredPrompt.prompt();
    deferredPrompt.userChoice
      .then(function (choice) {
        if (choice && choice.outcome === "accepted") {
          hideInstallButtons();
        }
      })
      .catch(function () {
        // Ignore prompt errors and keep the current UI state.
      })
      .finally(function () {
        deferredPrompt = null;
      });
  }

  function isOfflinePage() {
    return window.location.pathname === offlinePageUrl.pathname;
  }

  function redirectToOfflinePage() {
    if (isOfflinePage()) {
      return;
    }

    var nextUrl = new URL(offlinePageUrl.href);
    nextUrl.searchParams.set("from", window.location.pathname + window.location.search + window.location.hash);
    window.location.replace(nextUrl.href);
  }

  buttons.forEach(function (button) {
    button.addEventListener("click", handleInstallClick);
  });

  hideInstallButtons();

  window.addEventListener("beforeinstallprompt", function (event) {
    event.preventDefault();
    deferredPrompt = event;
    showInstallButtons();
  });

  window.addEventListener("appinstalled", function () {
    deferredPrompt = null;
    hideInstallButtons();
  });

  window.addEventListener("offline", function () {
    redirectToOfflinePage();
  });

  if (navigator.onLine === false) {
    redirectToOfflinePage();
  }

  if ("serviceWorker" in navigator && window.location.protocol !== "file:") {
    navigator.serviceWorker.register(new URL("sw.js", siteRoot), {
      scope: siteRoot.pathname
    }).catch(function (error) {
      console.warn("Service worker registration failed.", error);
    });
  }
})();
