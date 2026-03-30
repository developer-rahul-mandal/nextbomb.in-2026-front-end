(function () {
  var slugSources = Array.prototype.slice.call(document.querySelectorAll("[data-slug-source]"));

  function slugify(value) {
    return String(value || "")
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-+|-+$/g, "")
      .slice(0, 190);
  }

  slugSources.forEach(function (source) {
    var targetSelector = source.getAttribute("data-slug-target");
    var target = targetSelector ? document.querySelector(targetSelector) : null;

    if (!target) {
      return;
    }

    var touched = Boolean(target.value);

    target.addEventListener("input", function () {
      touched = target.value.trim() !== "";
    });

    source.addEventListener("input", function () {
      if (touched) {
        return;
      }

      target.value = slugify(source.value);
    });
  });
})();
