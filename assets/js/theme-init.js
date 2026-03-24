(function () {
  var storageKey = "nextbomb-theme";
  var root = document.documentElement;
  root.dataset.pageLoading = "true";
  window.__nextbombLoaderStart = Date.now();
  var media = window.matchMedia ? window.matchMedia("(prefers-color-scheme: dark)") : null;
  var prefersDark = Boolean(media && media.matches);
  var theme = prefersDark ? "dark" : "light";

  try {
    var savedTheme = localStorage.getItem(storageKey);
    if (savedTheme === "dark" || savedTheme === "light") {
      theme = savedTheme;
    }
  } catch (error) {
    // Ignore storage access issues and fall back to the system preference.
  }

  root.dataset.theme = theme;
})();
