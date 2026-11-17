document.getElementById("adminLoginForm").addEventListener("submit", function () {
    const button = document.getElementById("loginButton");
    button.disabled = true;
    button.textContent = "VERIFYING...";
});

const sidebar = document.getElementById("adminSidebar");
const overlay = document.getElementById("overlay");

function openSidebar() {
    sidebar.classList.remove("-translate-x-full");
    overlay.classList.remove("hidden");
}

function closeSidebar() {
    sidebar.classList.add("-translate-x-full");
    overlay.classList.add("hidden");
}

document.querySelectorAll(".nav-link[href^='#']").forEach(link => {
    link.addEventListener("click", () => {

        document.querySelectorAll(".nav-link").forEach(item => {
            item.classList.remove("active");
        });

        link.classList.add("active");

        if (window.innerWidth < 1024) {
            closeSidebar();
        }
    });
});
