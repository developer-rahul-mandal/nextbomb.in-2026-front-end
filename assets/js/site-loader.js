(function () {
  var root = document.documentElement;
  var minVisibleDuration = 280;
  var maxVisibleDuration = 4000;
  var exitDuration = 240;
  var loaderStart = window.__nextbombLoaderStart || Date.now();
  var scheduled = false;
  var cleared = false;

  function clearLoader() {
    if (cleared || root.getAttribute("data-page-loading") !== "true") {
      return;
    }

    cleared = true;
    root.dataset.pageLoaded = "true";

    window.setTimeout(function () {
      root.removeAttribute("data-page-loading");
      root.removeAttribute("data-page-loaded");
    }, exitDuration);
  }

  function scheduleClear() {
    if (scheduled) {
      return;
    }

    scheduled = true;

    var elapsed = Date.now() - loaderStart;
    var remaining = Math.max(0, minVisibleDuration - elapsed);
    window.setTimeout(clearLoader, remaining);
  }

  if (document.readyState === "complete") {
    scheduleClear();
  } else {
    window.addEventListener("load", scheduleClear, { once: true });
  }

  window.setTimeout(scheduleClear, maxVisibleDuration);

  window.addEventListener("pageshow", function (event) {
    if (!event.persisted) {
      return;
    }

    scheduled = true;
    cleared = true;
    root.removeAttribute("data-page-loading");
    root.removeAttribute("data-page-loaded");
  });
})();
