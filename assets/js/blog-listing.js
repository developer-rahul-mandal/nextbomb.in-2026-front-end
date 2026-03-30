(function () {
  var body = document.body;
  var form = document.getElementById("blog-search-form");
  var searchInput = document.getElementById("blog-search-input");
  var featuredPanel = document.getElementById("blog-featured-panel");
  var featuredCard = document.getElementById("blog-featured-card");
  var categoryFilter = document.getElementById("blog-category-filter");
  var summary = document.getElementById("blog-results-summary");
  var grid = document.getElementById("blog-post-grid");
  var pagination = document.getElementById("blog-pagination");

  if (!body || !form || !searchInput || !featuredPanel || !featuredCard || !categoryFilter || !summary || !grid || !pagination) {
    return;
  }

  var apiRoot = new URL((body.dataset.blogApiBase || "../api/blog").replace(/\/?$/, "/"), window.location.href);
  var siteRootUrl = new URL(body.dataset.siteRoot || "../", window.location.href);
  var pageBaseUrl = new URL("./", window.location.href);
  var state = readStateFromUrl();

  searchInput.value = state.search;

  function readStateFromUrl() {
    var params = new URLSearchParams(window.location.search);
    var page = parseInt(params.get("page") || "1", 10);

    return {
      search: (params.get("search") || "").trim(),
      category: (params.get("category") || "").trim(),
      page: !isNaN(page) && page > 0 ? page : 1,
      limit: 6
    };
  }

  function syncUrl() {
    var nextUrl = new URL(pageBaseUrl.toString());

    if (state.search) {
      nextUrl.searchParams.set("search", state.search);
    }

    if (state.category) {
      nextUrl.searchParams.set("category", state.category);
    }

    if (state.page > 1) {
      nextUrl.searchParams.set("page", String(state.page));
    }

    window.history.replaceState(null, "", nextUrl.pathname + nextUrl.search);
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function safeColor(value) {
    return /^#[0-9a-f]{6}$/i.test(String(value || "")) ? String(value) : "#b5512c";
  }

  function formatDate(value) {
    if (!value) {
      return "Date unavailable";
    }

    try {
      return new Intl.DateTimeFormat("en-US", {
        month: "short",
        day: "numeric",
        year: "numeric"
      }).format(new Date(value));
    } catch (error) {
      return value;
    }
  }

  function pluralize(count, noun) {
    return count === 1 ? noun : noun + "s";
  }

  function getCategoryLabel(categories, slug) {
    var index;

    for (index = 0; index < (categories || []).length; index += 1) {
      if (categories[index].slug === slug) {
        return categories[index].name;
      }
    }

    return slug.replace(/-/g, " ");
  }

  function resolveAsset(path) {
    if (!path) {
      return "";
    }

    if (/^(https?:)?\/\//i.test(path) || path.indexOf("data:") === 0) {
      return path;
    }

    return new URL(String(path).replace(/^\//, ""), siteRootUrl).toString();
  }

  function buildPostUrl(slug) {
    var url = new URL("post/", pageBaseUrl);
    url.searchParams.set("slug", slug);
    return url.toString();
  }

  function buildMeta(post) {
    return [
      "<span>" + escapeHtml(post.category.name) + "</span>",
      "<span>" + escapeHtml(formatDate(post.publishedAt)) + "</span>",
      "<span>" + escapeHtml(String(post.readingTime)) + " min read</span>",
      "<span>" + escapeHtml(String(post.viewCount || 0)) + " views</span>",
      "<span>" + escapeHtml(String(post.commentCount || 0)) + " comments</span>"
    ].join("");
  }

  function buildPageItems(currentPage, totalPages) {
    var pages = [];
    var start;
    var end;
    var page;

    if (totalPages <= 7) {
      for (page = 1; page <= totalPages; page += 1) {
        pages.push(page);
      }

      return pages;
    }

    pages.push(1);
    start = Math.max(2, currentPage - 2);
    end = Math.min(totalPages - 1, currentPage + 2);

    if (currentPage <= 4) {
      start = 2;
      end = 5;
    } else if (currentPage >= totalPages - 3) {
      start = totalPages - 4;
      end = totalPages - 1;
    }

    if (start > 2) {
      pages.push("ellipsis-start");
    }

    for (page = start; page <= end; page += 1) {
      pages.push(page);
    }

    if (end < totalPages - 1) {
      pages.push("ellipsis-end");
    }

    pages.push(totalPages);

    return pages;
  }

  function renderFeatured(post) {
    if (!post) {
      featuredPanel.hidden = true;
      return;
    }

    featuredPanel.hidden = false;

    var accent = safeColor(post.category.color);
    var href = buildPostUrl(post.slug);
    var coverImage = resolveAsset(post.coverImage);

    featuredCard.className = "blog-featured-card";
    featuredCard.style.setProperty("--blog-accent", accent);
    featuredCard.innerHTML =
      '<a class="blog-featured-media" href="' + href + '">' +
      '<img src="' + coverImage + '" alt="' + escapeHtml(post.coverAlt) + '" loading="lazy" />' +
      "</a>" +
      '<div class="blog-featured-body">' +
      '<div class="blog-card-meta">' + buildMeta(post) + "</div>" +
      "<h3>" + escapeHtml(post.title) + "</h3>" +
      "<p>" + escapeHtml(post.excerpt) + "</p>" +
      '<a class="button button-secondary" href="' + href + '">Read Featured Story</a>' +
      "</div>";
  }

  function renderCategories(categories) {
    var buttons = [
      '<button class="blog-filter-chip' + (state.category ? "" : " is-active") + '" type="button" data-category="" aria-pressed="' + String(!state.category) + '">All stories</button>'
    ];

    categories.forEach(function (category) {
      var isActive = state.category === category.slug;

      buttons.push(
        '<button class="blog-filter-chip' + (isActive ? " is-active" : "") + '" type="button" data-category="' + escapeHtml(category.slug) + '" aria-pressed="' + String(isActive) + '">' +
          '<span>' + escapeHtml(category.name) + "</span>" +
          '<strong>' + escapeHtml(String(category.postCount)) + "</strong>" +
        "</button>"
      );
    });

    categoryFilter.innerHTML = buttons.join("");
  }

  function renderPosts(posts) {
    if (!posts.length) {
      grid.innerHTML =
        '<article class="blog-empty-card">' +
        "<h3>No stories matched this view.</h3>" +
        "<p>Try clearing your search or choosing a different category.</p>" +
        "</article>";
      return;
    }

    grid.innerHTML = posts
      .map(function (post) {
        var accent = safeColor(post.category.color);
        var href = buildPostUrl(post.slug);

        return (
          '<article class="blog-card" style="--blog-accent: ' + accent + ';">' +
            '<a class="blog-card-media" href="' + href + '">' +
              '<img src="' + resolveAsset(post.coverImage) + '" alt="' + escapeHtml(post.coverAlt) + '" loading="lazy" />' +
            "</a>" +
            '<div class="blog-card-body">' +
              '<div class="blog-card-meta">' + buildMeta(post) + "</div>" +
              "<h3><a href=\"" + href + "\">" + escapeHtml(post.title) + "</a></h3>" +
              "<p>" + escapeHtml(post.excerpt) + "</p>" +
              '<a class="blog-card-link" href="' + href + '">Read story</a>' +
            "</div>" +
          "</article>"
        );
      })
      .join("");
  }

  function renderSummary(payload) {
    var total = payload.totals && payload.totals.storyCount ? payload.totals.storyCount : payload.pagination.total + (payload.featured ? 1 : 0);
    var parts = [];

    if (!total) {
      summary.textContent = "No stories matched your current filters.";
      return;
    }

    parts.push("Showing " + total + " " + pluralize(total, "story"));

    if (state.category) {
      parts.push("in " + getCategoryLabel(payload.categories || [], state.category));
    }

    if (state.search) {
      parts.push('for "' + state.search + '"');
    }

    summary.textContent = parts.join(" ");
  }

  function renderPagination(paging) {
    if (paging.totalPages <= 1) {
      pagination.innerHTML = "";
      pagination.hidden = true;
      return;
    }

    pagination.hidden = false;
    pagination.innerHTML =
      '<div class="blog-pagination-track">' +
        '<button class="blog-page-button blog-page-jump" type="button" data-page="1"' + (paging.page === 1 ? " disabled" : "") + ' aria-label="First page">&lt;&lt;</button>' +
        '<button class="blog-page-button blog-page-jump" type="button" data-page="' + String(Math.max(1, paging.page - 1)) + '"' + (paging.hasPrevious ? "" : " disabled") + ' aria-label="Previous page">&lt;</button>' +
        buildPageItems(paging.page, paging.totalPages).map(function (item) {
          if (typeof item === "string" && item.indexOf("ellipsis") === 0) {
            return '<span class="blog-page-ellipsis" aria-hidden="true">...</span>';
          }

          return '<button class="blog-page-button' + (item === paging.page ? " is-current" : "") + '" type="button" data-page="' + String(item) + '"' + (item === paging.page ? ' aria-current="page"' : "") + '>' + escapeHtml(String(item)) + "</button>";
        }).join("") +
        '<button class="blog-page-button blog-page-jump" type="button" data-page="' + String(Math.min(paging.totalPages, paging.page + 1)) + '"' + (paging.hasNext ? "" : " disabled") + ' aria-label="Next page">&gt;</button>' +
        '<button class="blog-page-button blog-page-jump" type="button" data-page="' + String(paging.totalPages) + '"' + (paging.page === paging.totalPages ? " disabled" : "") + ' aria-label="Last page">&gt;&gt;</button>' +
      "</div>" +
      '<span class="blog-page-status">Page ' + escapeHtml(String(paging.page)) + " of " + escapeHtml(String(paging.totalPages)) + "</span>";
  }

  function renderLoadingState() {
    featuredPanel.hidden = Boolean(state.category || state.search);
    featuredCard.className = "blog-featured-card is-loading";
    featuredCard.innerHTML = "<p>Loading featured story...</p>";
    summary.textContent = "Loading stories...";
    grid.innerHTML = [
      '<article class="blog-card is-loading"></article>',
      '<article class="blog-card is-loading"></article>',
      '<article class="blog-card is-loading"></article>'
    ].join("");
    pagination.innerHTML = "";
    pagination.hidden = true;
  }

  function renderError(message) {
    featuredPanel.hidden = true;
    summary.textContent = "Unable to load the blog right now.";
    grid.innerHTML =
      '<article class="blog-empty-card">' +
      "<h3>Blog setup still needs one step.</h3>" +
      "<p>" + escapeHtml(message) + "</p>" +
      "<p>Import <code>database/blog_schema.sql</code> and confirm the database values in <code>config/blog.php</code>.</p>" +
      "</article>";
    pagination.innerHTML = "";
    pagination.hidden = true;
  }

  function loadPosts() {
    renderLoadingState();

    var requestUrl = new URL("posts.php", apiRoot);
    requestUrl.searchParams.set("page", String(state.page));
    requestUrl.searchParams.set("limit", String(state.limit));

    if (state.search) {
      requestUrl.searchParams.set("search", state.search);
    }

    if (state.category) {
      requestUrl.searchParams.set("category", state.category);
    }

    fetch(requestUrl.toString(), {
      headers: {
        Accept: "application/json"
      }
    })
      .then(function (response) {
        return response
          .json()
          .catch(function () {
            return null;
          })
          .then(function (payload) {
            if (!response.ok || !payload || payload.success === false) {
              throw new Error(payload && payload.message ? payload.message : "Unable to load blog stories.");
            }

            return payload;
          });
      })
      .then(function (payload) {
        renderFeatured(payload.featured);
        renderCategories(payload.categories || []);
        renderSummary(payload);
        renderPosts(payload.posts || []);
        renderPagination(payload.pagination);
      })
      .catch(function (error) {
        renderError(error && error.message ? error.message : "Unable to load blog stories.");
      });
  }

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    state.search = searchInput.value.trim();
    state.page = 1;
    syncUrl();
    loadPosts();
  });

  categoryFilter.addEventListener("click", function (event) {
    var button = event.target.closest("button[data-category]");

    if (!button) {
      return;
    }

    state.category = button.getAttribute("data-category") || "";
    state.page = 1;
    syncUrl();
    loadPosts();
  });

  pagination.addEventListener("click", function (event) {
    var button = event.target.closest("button[data-page]");

    if (!button || button.disabled) {
      return;
    }

    state.page = Math.max(1, parseInt(button.getAttribute("data-page") || "1", 10));
    syncUrl();
    loadPosts();
  });

  window.addEventListener("popstate", function () {
    state = readStateFromUrl();
    searchInput.value = state.search;
    loadPosts();
  });

  loadPosts();
})();
