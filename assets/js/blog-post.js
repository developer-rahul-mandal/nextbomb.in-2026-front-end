(function () {
  var form = document.getElementById("blog-comment-form");
  var feedback = document.getElementById("blog-comment-feedback");

  if (!form || !feedback) {
    return;
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function setFeedback(type, message) {
    feedback.hidden = false;
    feedback.className = "blog-comment-feedback is-" + type;
    feedback.innerHTML = "<p>" + escapeHtml(message) + "</p>";
  }

  form.addEventListener("submit", function (event) {
    event.preventDefault();

    var submitButton = form.querySelector('button[type="submit"]');
    var formData = new FormData(form);

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = "Submitting...";
    }

    fetch(form.action, {
      method: "POST",
      body: formData,
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
              throw new Error(payload && payload.message ? payload.message : "Unable to submit your comment.");
            }

            return payload;
          });
      })
      .then(function (payload) {
        form.reset();
        setFeedback("success", payload.message || "Comment received and queued for review.");
      })
      .catch(function (error) {
        setFeedback("error", error && error.message ? error.message : "Unable to submit your comment.");
      })
      .finally(function () {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = "Submit Comment";
        }
      });
  });
})();
