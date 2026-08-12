(function () {
  var changeButton = document.getElementById("changePasswordBtn");
  var cancelButton = document.getElementById("cancelPasswordBtn");
  var box = document.getElementById("passwordChangeBox");
  var photoButton = document.getElementById("profilePhotoBtn");
  var photoInput = document.getElementById("profilePhotoInput");

  if (changeButton && cancelButton && box) {
    changeButton.addEventListener("click", function () { changeButton.style.display = "none"; box.classList.add("is-visible"); box.setAttribute("aria-hidden", "false"); });
    cancelButton.addEventListener("click", function () { box.classList.remove("is-visible"); box.setAttribute("aria-hidden", "true"); changeButton.style.display = ""; });
  }

  if (!photoButton || !photoInput) return;
  photoButton.addEventListener("click", function () { photoInput.click(); });
  photoInput.addEventListener("change", function () {
    var file = photoInput.files && photoInput.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function () { photoButton.style.backgroundImage = "url('" + reader.result + "')"; };
    reader.readAsDataURL(file);
  });
})();
