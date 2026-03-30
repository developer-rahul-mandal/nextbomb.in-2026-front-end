(function () {
  var AD_BLOCK_STYLE_ID = "site-guard-adblock-styles";
  var adBlockPromptVisible = false;
  var adBlockPromptDismissed = false;
  var adBlockCheckPromise = null;
  var AD_PROBE_SCRIPT_SRC = "https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js";

  function blockEvent(event) {
    event.preventDefault();
    event.stopPropagation();
  }

  function isBlockedShortcut(event) {
    var key = String(event.key || "").toLowerCase();
    var isMacInspectCombo = event.metaKey && event.altKey && (key === "i" || key === "j" || key === "c");
    var isWindowsInspectCombo =
      event.ctrlKey && event.shiftKey && (key === "i" || key === "j" || key === "c" || key === "k");
    var isViewSourceCombo = (event.ctrlKey || event.metaKey) && key === "u";

    return key === "f12" || isMacInspectCombo || isWindowsInspectCombo || isViewSourceCombo;
  }

  function onReady(callback) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", callback, { once: true });
      return;
    }

    callback();
  }

  function injectAdBlockStyles() {
    var existingStyle = document.getElementById(AD_BLOCK_STYLE_ID);

    if (existingStyle || !document.head) {
      return;
    }

    var style = document.createElement("style");
    style.id = AD_BLOCK_STYLE_ID;
    style.textContent =
      ":root {" +
      "  --site-guard-backdrop: rgba(15, 23, 34, 0.72);" +
      "  --site-guard-panel: #fff8ef;" +
      "  --site-guard-panel-strong: #fff2de;" +
      "  --site-guard-text: #221712;" +
      "  --site-guard-muted: rgba(34, 23, 18, 0.72);" +
      "  --site-guard-border: rgba(181, 81, 44, 0.18);" +
      "  --site-guard-accent: #b5512c;" +
      "  --site-guard-accent-strong: #8f3f20;" +
      "  --site-guard-secondary: rgba(34, 23, 18, 0.06);" +
      "  --site-guard-shadow: 0 24px 60px rgba(18, 16, 13, 0.28);" +
      "}" +
      "html[data-theme='dark'] {" +
      "  --site-guard-backdrop: rgba(3, 8, 14, 0.78);" +
      "  --site-guard-panel: #121d29;" +
      "  --site-guard-panel-strong: #1a2938;" +
      "  --site-guard-text: #f7efe3;" +
      "  --site-guard-muted: rgba(247, 239, 227, 0.76);" +
      "  --site-guard-border: rgba(255, 154, 90, 0.22);" +
      "  --site-guard-accent: #ff9a5a;" +
      "  --site-guard-accent-strong: #ffb37f;" +
      "  --site-guard-secondary: rgba(255, 255, 255, 0.06);" +
      "  --site-guard-shadow: 0 28px 64px rgba(0, 0, 0, 0.44);" +
      "}" +
      "html[data-adblock-detected='true'] {" +
      "  overflow: hidden;" +
      "}" +
      ".site-guard-adblock {" +
      "  position: fixed;" +
      "  inset: 0;" +
      "  z-index: 2147483647;" +
      "  display: grid;" +
      "  place-items: center;" +
      "  padding: 1rem;" +
      "}" +
      ".site-guard-adblock__backdrop {" +
      "  position: absolute;" +
      "  inset: 0;" +
      "  background: var(--site-guard-backdrop);" +
      "  backdrop-filter: blur(10px);" +
      "}" +
      ".site-guard-adblock__dialog {" +
      "  position: relative;" +
      "  width: min(100%, 40rem);" +
      "  padding: 1.5rem;" +
      "  border-radius: 1.5rem;" +
      "  border: 1px solid var(--site-guard-border);" +
      "  background: linear-gradient(180deg, var(--site-guard-panel-strong) 0%, var(--site-guard-panel) 100%);" +
      "  color: var(--site-guard-text);" +
      "  box-shadow: var(--site-guard-shadow);" +
      "}" +
      ".site-guard-adblock__badge {" +
      "  display: inline-flex;" +
      "  align-items: center;" +
      "  gap: 0.5rem;" +
      "  padding: 0.5rem 0.85rem;" +
      "  border-radius: 999px;" +
      "  background: rgba(181, 81, 44, 0.12);" +
      "  color: var(--site-guard-accent);" +
      "  font-size: 0.86rem;" +
      "  font-weight: 700;" +
      "  letter-spacing: 0.04em;" +
      "  text-transform: uppercase;" +
      "}" +
      ".site-guard-adblock__title {" +
      "  margin: 1rem 0 0.75rem;" +
      "  font-size: clamp(1.65rem, 4vw, 2.25rem);" +
      "  line-height: 1.1;" +
      "}" +
      ".site-guard-adblock__copy {" +
      "  margin: 0;" +
      "  color: var(--site-guard-muted);" +
      "  line-height: 1.7;" +
      "}" +
      ".site-guard-adblock__copy + .site-guard-adblock__copy {" +
      "  margin-top: 0.7rem;" +
      "}" +
      ".site-guard-adblock__actions {" +
      "  display: flex;" +
      "  flex-wrap: wrap;" +
      "  gap: 0.75rem;" +
      "  margin-top: 1.35rem;" +
      "}" +
      ".site-guard-adblock__button {" +
      "  appearance: none;" +
      "  border: 1px solid transparent;" +
      "  border-radius: 999px;" +
      "  padding: 0.9rem 1.25rem;" +
      "  font: inherit;" +
      "  font-weight: 700;" +
      "  cursor: pointer;" +
      "  transition: transform 160ms ease, background-color 160ms ease, border-color 160ms ease;" +
      "}" +
      ".site-guard-adblock__button:hover," +
      ".site-guard-adblock__button:focus-visible {" +
      "  transform: translateY(-1px);" +
      "  outline: none;" +
      "}" +
      ".site-guard-adblock__button--primary {" +
      "  background: var(--site-guard-accent);" +
      "  color: #fff7ee;" +
      "}" +
      ".site-guard-adblock__button--primary:hover," +
      ".site-guard-adblock__button--primary:focus-visible {" +
      "  background: var(--site-guard-accent-strong);" +
      "}" +
      ".site-guard-adblock__button--secondary {" +
      "  background: var(--site-guard-secondary);" +
      "  border-color: var(--site-guard-border);" +
      "  color: var(--site-guard-text);" +
      "}" +
      ".site-guard-adblock__footnote {" +
      "  margin: 1rem 0 0;" +
      "  color: var(--site-guard-muted);" +
      "  font-size: 0.95rem;" +
      "}" +
      ".site-guard-adbait {" +
      "  position: absolute !important;" +
      "  left: -9999px !important;" +
      "  top: -9999px !important;" +
      "  width: 10px !important;" +
      "  height: 10px !important;" +
      "  pointer-events: none !important;" +
      "}" +
      "@media (max-width: 640px) {" +
      "  .site-guard-adblock__dialog {" +
      "    padding: 1.25rem;" +
      "    border-radius: 1.2rem;" +
      "  }" +
      "  .site-guard-adblock__actions {" +
      "    flex-direction: column;" +
      "  }" +
      "  .site-guard-adblock__button {" +
      "    width: 100%;" +
      "  }" +
      "}";
    document.head.appendChild(style);
  }

  function removeNode(node) {
    if (node && node.parentNode) {
      node.parentNode.removeChild(node);
    }
  }

  function createAdProbeElements() {
    var hiddenProbeCss =
      "pointer-events:none;height:1px;width:1px;opacity:0;visibility:hidden;position:fixed;bottom:0;left:0;";
    var slotProbe = document.createElement("div");
    var classProbe = document.createElement("div");
    var insProbe = document.createElement("ins");

    slotProbe.id = "div-gpt-ad-3061307416813-0";
    slotProbe.style.cssText = hiddenProbeCss;

    classProbe.className = "textads banner-ads banner_ads ad-unit ad-zone ad-space adsbox ads";
    classProbe.style.cssText = hiddenProbeCss;

    insProbe.className = "adsbygoogle";
    insProbe.style.cssText = hiddenProbeCss;

    return [slotProbe, classProbe, insProbe];
  }

  function removeNodes(nodes) {
    if (!nodes || !nodes.length) {
      return;
    }

    for (var index = 0; index < nodes.length; index += 1) {
      removeNode(nodes[index]);
    }
  }

  function hasBlockedProbeElement(element) {
    if (!element || !window.getComputedStyle) {
      return false;
    }

    var computedStyle = window.getComputedStyle(element);

    return computedStyle.display === "none" || element.offsetHeight === 0 || element.clientHeight === 0;
  }

  function detectCosmeticAdBlock() {
    if (!document.body) {
      return Promise.resolve(false);
    }

    var probes = createAdProbeElements();

    for (var index = 0; index < probes.length; index += 1) {
      document.body.appendChild(probes[index]);
    }

    return new Promise(function (resolve) {
      window.setTimeout(function () {
        var isBlocked = hasBlockedProbeElement(probes[0]) || hasBlockedProbeElement(probes[1]);

        removeNodes(probes);
        resolve(isBlocked);
      }, 60);
    });
  }

  function detectNetworkAdBlock() {
    if (!document.head || window.navigator.onLine === false) {
      return Promise.resolve(false);
    }

    return new Promise(function (resolve) {
      var probeScript = document.createElement("script");
      var timeoutId = 0;
      var settled = false;

      function finish(isBlocked) {
        if (settled) {
          return;
        }

        settled = true;
        window.clearTimeout(timeoutId);
        probeScript.onload = null;
        probeScript.onerror = null;
        removeNode(probeScript);
        resolve(isBlocked);
      }

      probeScript.async = true;
      probeScript.crossOrigin = "anonymous";
      probeScript.src = AD_PROBE_SCRIPT_SRC;
      probeScript.onload = function () {
        finish(false);
      };
      probeScript.onerror = function () {
        finish(true);
      };

      timeoutId = window.setTimeout(function () {
        finish(true);
      }, 2800);

      document.head.appendChild(probeScript);
    });
  }

  function isAdBlockActive() {
    return detectCosmeticAdBlock().then(function (isCosmeticallyBlocked) {
      if (isCosmeticallyBlocked) {
        return true;
      }

      return detectNetworkAdBlock();
    });
  }

  function closeAdBlockPrompt(promptRoot) {
    removeNode(promptRoot);
    document.documentElement.removeAttribute("data-adblock-detected");
    adBlockPromptVisible = false;
    adBlockPromptDismissed = true;
  }

  function showAdBlockPrompt() {
    if (adBlockPromptVisible || !document.body) {
      return;
    }

    injectAdBlockStyles();

    var promptRoot = document.createElement("div");
    promptRoot.className = "site-guard-adblock";
    promptRoot.innerHTML =
      '<div class="site-guard-adblock__backdrop" aria-hidden="true"></div>' +
      '<section class="site-guard-adblock__dialog" role="dialog" aria-modal="true" aria-labelledby="site-guard-adblock-title">' +
      '  <span class="site-guard-adblock__badge">Ads keep NEXTBOMB free</span>' +
      '  <h2 class="site-guard-adblock__title" id="site-guard-adblock-title">Please allow ads on NEXTBOMB</h2>' +
      '  <p class="site-guard-adblock__copy">It looks like an ad blocker is active. Our tools stay free because ads help us cover hosting, maintenance, and new updates.</p>' +
      '  <p class="site-guard-adblock__copy">Please whitelist NEXTBOMB in your ad blocker and then refresh this page.</p>' +
      '  <div class="site-guard-adblock__actions">' +
      '    <button class="site-guard-adblock__button site-guard-adblock__button--primary" type="button" data-adblock-action="refresh">I allowed ads, refresh</button>' +
      '    <!-- <button class="site-guard-adblock__button site-guard-adblock__button--secondary" type="button" data-adblock-action="dismiss">Continue for now</button> -->' +
      "  </div>" +
      '  <p class="site-guard-adblock__footnote">Thanks for supporting a free toolset instead of adding a paywall.</p>' +
      "</section>";

    document.documentElement.setAttribute("data-adblock-detected", "true");
    document.body.appendChild(promptRoot);
    adBlockPromptVisible = true;

    var refreshButton = promptRoot.querySelector('[data-adblock-action="refresh"]');
    var dismissButton = promptRoot.querySelector('[data-adblock-action="dismiss"]');

    if (refreshButton) {
      refreshButton.addEventListener("click", function () {
        window.location.reload();
      });
      refreshButton.focus();
    }

    // if (dismissButton) {
    //   dismissButton.addEventListener("click", function () {
    //     closeAdBlockPrompt(promptRoot);
    //   });
    // }
  }

  function runAdBlockCheck() {
    if (adBlockPromptVisible || adBlockPromptDismissed || adBlockCheckPromise) {
      return;
    }

    adBlockCheckPromise = isAdBlockActive()
      .then(function (isBlocked) {
        if (isBlocked && !adBlockPromptDismissed) {
          showAdBlockPrompt();
        }
      })
      .finally(function () {
        adBlockCheckPromise = null;
      });
  }

  window.addEventListener("contextmenu", blockEvent, { capture: true });
  window.addEventListener(
    "keydown",
    function (event) {
      if (isBlockedShortcut(event)) {
        blockEvent(event);
      }
    },
    { capture: true }
  );

  onReady(function () {
    window.setTimeout(runAdBlockCheck, 180);
    window.addEventListener(
      "load",
      function () {
        window.setTimeout(runAdBlockCheck, 0);
      },
      { once: true }
    );
  });
})();
