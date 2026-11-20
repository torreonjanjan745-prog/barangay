
document.addEventListener("DOMContentLoaded", function () {

    // ============================================
    // MOBILE SIDEBAR
    // ============================================

    const menuBtn = document.getElementById("menuBtn");
    const closeMenuBtn = document.getElementById("closeMenuBtn");
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("overlay");

    function openSidebar() {
        if (sidebar) {
            sidebar.classList.remove("-translate-x-full");
            sidebar.classList.add("show");
        }

        if (overlay) {
            overlay.classList.remove("hidden");
        }
    }

    function closeSidebar() {
        if (sidebar) {
            sidebar.classList.add("-translate-x-full");
            sidebar.classList.remove("show");
        }

        if (overlay) {
            overlay.classList.add("hidden");
        }
    }

    if (menuBtn && sidebar && overlay) {
        menuBtn.addEventListener("click", function () {
            const isOpen =
                sidebar.classList.contains("show") ||
                !sidebar.classList.contains("-translate-x-full");

            if (isOpen) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });

        overlay.addEventListener("click", closeSidebar);

        if (closeMenuBtn) {
            closeMenuBtn.addEventListener("click", closeSidebar);
        }

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                closeSidebar();
            }
        });
    }

    // ============================================
    // PROFILE UPDATE CONFIRMATION
    // ============================================

    const profileForm = document
        .querySelector('button[name="update_profile"]')
        ?.closest("form");

    if (profileForm) {
        profileForm.addEventListener("submit", function (event) {

            if (!confirm("Are you sure you want to update your profile?")) {
                event.preventDefault();
            }

        });
    }

    // ============================================
    // PASSWORD CHANGE VALIDATION
    // ============================================

    const passwordForm = document.getElementById("passwordForm");

    if (passwordForm) {
        passwordForm.addEventListener("submit", function (event) {

            const newPassword = passwordForm.querySelector(
                '[name="new_password"]'
            );

            const confirmPassword = passwordForm.querySelector(
                '[name="confirm_password"]'
            );

            if (!newPassword || !confirmPassword) {
                return;
            }

            if (newPassword.value !== confirmPassword.value) {

                event.preventDefault();

                alert("New password and confirm password do not match!");

                return;
            }

            if (!confirm("Are you sure you want to change your password?")) {
                event.preventDefault();
            }

        });
    }

    // ============================================
    // PASSWORD SHOW / HIDE
    // ============================================

    document.querySelectorAll(".password-toggle").forEach(function (button) {

        button.addEventListener("click", function () {

            const input = this.parentElement.querySelector("input");

            if (!input) return;

            const show = input.type === "password";

            input.type = show ? "text" : "password";

            this.textContent = show ? "Hide" : "Show";

        });

    });

    // ============================================
    // LOGIN PASSWORD TOGGLE
    // ============================================

    const togglePassword = document.getElementById("togglePassword");
    const password = document.getElementById("password");

    if (togglePassword && password) {

        togglePassword.addEventListener("click", function () {

            const show = password.type === "password";

            password.type = show ? "text" : "password";

            togglePassword.innerHTML = show
                ? '<i data-lucide="eye-off" class="w-5 h-5"></i>'
                : '<i data-lucide="eye" class="w-5 h-5"></i>';

            togglePassword.setAttribute(
                "aria-label",
                show ? "Hide password" : "Show password"
            );

            if (window.lucide) {
                window.lucide.createIcons();
            }

        });

    }

    // ============================================
    // INITIALIZE LUCIDE ICONS
    // ============================================

    if (window.lucide) {
        window.lucide.createIcons();
    }

});
