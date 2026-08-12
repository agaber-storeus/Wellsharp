(function () {
  var shell = document.querySelector(".proctor-shell");
  var button = document.querySelector(".collapse-btn");

  if (!shell || !button) {
    return;
  }

  if (localStorage.getItem("wellsharpProctorMenu") === "collapsed") {
    shell.classList.add("sidebar-collapsed");
  }

  function syncLabel() {
    button.textContent = shell.classList.contains("sidebar-collapsed")
      ? ""
      : "Collapse";
  }

  syncLabel();

  button.addEventListener("click", function () {
    shell.classList.toggle("sidebar-collapsed");
    localStorage.setItem(
      "wellsharpProctorMenu",
      shell.classList.contains("sidebar-collapsed") ? "collapsed" : "open"
    );
    syncLabel();
  });
})();
