(function () {
  var storageKey = "nextbomb-theme";
  var root = document.documentElement;
  var buttons = Array.prototype.slice.call(document.querySelectorAll(".theme-toggle"));
  var media = window.matchMedia ? window.matchMedia("(prefers-color-scheme: dark)") : null;
  var themeMeta = document.querySelector('meta[name="theme-color"]');
  var themeColors = {
    light: "#fbf6ee",
    dark: "#09111b"
  };

  function getStoredTheme() {
    try {
      return localStorage.getItem(storageKey);
    } catch (error) {
      return null;
    }
  }

  function saveTheme(theme) {
    try {
      localStorage.setItem(storageKey, theme);
    } catch (error) {
      // Ignore storage access issues and keep the in-memory theme.
    }
  }

  function resolveTheme() {
    var storedTheme = getStoredTheme();
    if (storedTheme === "dark" || storedTheme === "light") {
      return storedTheme;
    }

    return media && media.matches ? "dark" : "light";
  }

  function syncUi(theme) {
    root.dataset.theme = theme;

    if (themeMeta) {
      themeMeta.setAttribute("content", themeColors[theme] || themeColors.light);
    }

    buttons.forEach(function (button) {
      var isDark = theme === "dark";
      button.dataset.themeState = theme;
      button.setAttribute("aria-pressed", String(isDark));
      button.setAttribute("aria-label", isDark ? "Switch to light mode" : "Switch to dark mode");
      button.title = isDark ? "Switch to light mode" : "Switch to dark mode";
    });
  }

  function setTheme(theme, shouldPersist) {
    if (shouldPersist) {
      saveTheme(theme);
    }

    syncUi(theme);
  }

  syncUi(resolveTheme());

  buttons.forEach(function (button) {
    button.addEventListener("click", function () {
      var nextTheme = root.dataset.theme === "dark" ? "light" : "dark";
      setTheme(nextTheme, true);
    });
  });

  if (media) {
    var handleSystemChange = function (event) {
      if (getStoredTheme()) {
        return;
      }

      syncUi(event.matches ? "dark" : "light");
    };

    if (typeof media.addEventListener === "function") {
      media.addEventListener("change", handleSystemChange);
    } else if (typeof media.addListener === "function") {
      media.addListener(handleSystemChange);
    }
  }
})();
