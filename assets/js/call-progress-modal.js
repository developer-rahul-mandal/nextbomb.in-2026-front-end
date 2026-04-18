(function () {
  var form = document.getElementById("call-bomber-form");
  var modal = document.getElementById("call-progress-modal");
  var timerOutput = document.getElementById("call-timer");
  var counterOutput = document.getElementById("call-counter");
  var closeButtons = document.querySelectorAll("[data-call-modal-close]");
  var timerInterval = 0;
  var counterInterval = 0;
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

  function stopProgress() {
    window.clearInterval(timerInterval);
    window.clearInterval(counterInterval);
    timerInterval = 0;
    counterInterval = 0;
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
    document.body.classList.add("call-bomber-modal-open");

    window.requestAnimationFrame(function () {
      modal.classList.add("is-open");
      var closeButton = modal.querySelector(".call-progress-modal__close");

      if (closeButton) {
        closeButton.focus();
      }
    });

    startProgress();
  }

  function closeModal() {
    stopProgress();
    modal.classList.remove("is-open");
    document.body.classList.remove("call-bomber-modal-open");

    window.setTimeout(function () {
      if (!modal.classList.contains("is-open")) {
        modal.hidden = true;
      }
    }, 180);
  }

  if (form && modal && timerOutput && counterOutput) {
    form.addEventListener("submit", function (event) {
      event.preventDefault();

      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      openModal();
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
