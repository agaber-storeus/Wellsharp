(function () {
  var labels = ["Details", "Settings", "Solution"];

  function normalizeStepLabels() {
    document.querySelectorAll(".question-wizard .wizard-tab").forEach(function (tab, index) {
      var number = document.createElement("span");
      number.className = "wizard-step-number";
      number.textContent = String(index + 1);

      var label = document.createElement("span");
      label.textContent = labels[index] || "Step " + String(index + 1);

      tab.replaceChildren(number, label);
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", normalizeStepLabels, { once: true });
  } else {
    normalizeStepLabels();
  }
})();
