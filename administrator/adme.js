function toggleAdminPassword() {

    const input = document.getElementById("adminPassword");
    const icon = document.getElementById("adminEye");

    if (!input || !icon) return;

    if (input.type === "password") {
        input.type = "text";
        icon.setAttribute("data-lucide", "eye-off");
    } else {
        input.type = "password";
        icon.setAttribute("data-lucide", "eye");
    }

    lucide.createIcons();
}

document.addEventListener("DOMContentLoaded", function () {
    if (window.lucide) {
        lucide.createIcons();
    }
});

document.addEventListener("DOMContentLoaded", function () {
    if (window.lucide) {
        lucide.createIcons();
    }
});

const menuBtn = document.getElementById("menuBtn");
const sidebar = document.getElementById("sidebar");
const overlay = document.getElementById("overlay");

menuBtn.addEventListener("click", function () {

    sidebar.classList.toggle("show");
    overlay.classList.toggle("hidden");

});

overlay.addEventListener("click", function () {

    sidebar.classList.remove("show");
    overlay.classList.add("hidden");

});

lucide.createIcons();
