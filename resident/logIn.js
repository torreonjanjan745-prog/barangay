
const loginForm = document.getElementById("loginForm");
const loginButton = document.getElementById("loginButton");

loginForm.addEventListener("submit", function () {
    loginButton.disabled = true;
    loginButton.textContent = "CHECKING...";
    loginButton.classList.add("opacity-60", "cursor-not-allowed");
});
