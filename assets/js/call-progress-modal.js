(function () {
  var form = document.getElementById("bomber-form");
  var modal = document.getElementById("progress-modal");
  var phoneInput = document.getElementById("bomber-input");
  var timerOutput = document.getElementById("modal-timer");
  var counterOutput = document.getElementById("wave-counter");
  var encryptedOutput = document.getElementById("encrypted-output");
  var closeButtons = document.querySelectorAll("[data-modal-close]");
  var timerInterval = 0;
  var counterInterval = 0;
  var encryptionAbortController = null;
  var elapsedSeconds = 0;
  var counterValue = 0;

  function formatTime(totalSeconds) {
    var minutes = Math.floor(totalSeconds / 60);
    var seconds = totalSeconds % 60;

    return String(minutes).padStart(2, "0") + ":" + String(seconds).padStart(2, "0");
  }

  function resetProgress() {
    elapsedSeconds = 0;
    counterValue = 0;
    timerOutput.textContent = "00:00";
    counterOutput.textContent = "0";
  }

  function setEncryptedNumber(message, isError) {
    var encryptedBox = encryptedOutput ? encryptedOutput.closest(".progress-modal__encrypted") : null;

    if (!encryptedOutput) {
      return;
    }

    encryptedOutput.textContent = message;

    if (encryptedBox) {
      encryptedBox.classList.toggle("is-error", Boolean(isError));
    }
  }

  function resetEncryptedNumber() {
    setEncryptedNumber("Waiting for encryption...", false);
  }

  function stopProgress() {
    window.clearInterval(timerInterval);
    window.clearInterval(counterInterval);
    timerInterval = 0;
    counterInterval = 0;
  }

  function stopEncryptionRequest() {
    if (encryptionAbortController) {
      encryptionAbortController.abort();
      encryptionAbortController = null;
    }
  }

  function readJsonResponse(response) {
    return response.json().catch(function () {
      return {};
    }).then(function (payload) {
      if (!response.ok || payload.ok === false) {
        throw new Error(payload.message || "Encryption request failed.");
      }

      return payload;
    });
  }

  function requestEncryptedNumber(number) {
    var canAbort = typeof window.AbortController === "function";
    var tokenOptions = {
      credentials: "same-origin",
      headers: {
        Accept: "application/json"
      }
    };

    stopEncryptionRequest();
    setEncryptedNumber("Encrypting number...", false);

    if (!window.fetch) {
      setEncryptedNumber("Encryption API is not available in this browser.", true);
      return;
    }

    if (canAbort) {
      encryptionAbortController = new window.AbortController();
      tokenOptions.signal = encryptionAbortController.signal;
    }

    window.fetch("../api/enc/token.php", tokenOptions)
      .then(readJsonResponse)
      .then(function (tokenPayload) {
        if (!tokenPayload.token) {
          throw new Error("Encryption token was not created.");
        }

        var submitOptions = {
          method: "POST",
          credentials: "same-origin",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json"
          },
          body: JSON.stringify({
            number: number
          })
        };

        if (encryptionAbortController) {
          submitOptions.signal = encryptionAbortController.signal;
        }

        return window.fetch("../api/enc/" + encodeURIComponent(tokenPayload.token), submitOptions);
      })
      .then(readJsonResponse)
      .then(function (encryptPayload) {
        setEncryptedNumber(encryptPayload.encryptedNumber || "Encrypted number unavailable.", false);
      })
      .catch(function (error) {
        if (error && error.name === "AbortError") {
          return;
        }

        setEncryptedNumber(error && error.message ? error.message : "Encryption failed. Try again.", true);
      });
  }

  function startProgress() {
    stopProgress();
    resetProgress();

    timerInterval = window.setInterval(function () {
      elapsedSeconds += 1;
      timerOutput.textContent = formatTime(elapsedSeconds);
    }, 1000);

    counterInterval = window.setInterval(function () {
      counterValue += 1;
      counterOutput.textContent = String(counterValue);
    }, 3000);
  }

  function openModal() {
    modal.hidden = false;
    document.body.classList.add("bomber-modal-open");
    resetEncryptedNumber();

    window.requestAnimationFrame(function () {
      modal.classList.add("is-open");
      var closeButton = modal.querySelector(".progress-modal__close");

      if (closeButton) {
        closeButton.focus();
      }
    });

    startProgress();
  }

  function closeModal() {
    stopEncryptionRequest();
    stopProgress();
    modal.classList.remove("is-open");
    document.body.classList.remove("bomber-modal-open");

    window.setTimeout(function () {
      if (!modal.classList.contains("is-open")) {
        modal.hidden = true;
      }
    }, 180);
  }

  if (form && modal && phoneInput && timerOutput && counterOutput) {
    form.addEventListener("submit", function (event) {
      event.preventDefault();

      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      openModal();
      requestEncryptedNumber(phoneInput.value);
    });

    for (var index = 0; index < closeButtons.length; index += 1) {
      closeButtons[index].addEventListener("click", closeModal);
    }

    window.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && !modal.hidden) {
        closeModal();
      }
    });
  }
})();
