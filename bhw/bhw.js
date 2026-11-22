
document.addEventListener("DOMContentLoaded", function () {

    // ============================================
    // INITIALIZE LUCIDE ICONS
    // ============================================

    function initializeIcons() {
        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    initializeIcons();


    // ============================================
    // MOBILE SIDEBAR
    // ============================================

    const menuBtn = document.getElementById("menuBtn");
    const closeMenuBtn = document.getElementById("closeMenuBtn");
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("overlay");

    function openSidebar() {

        if (!sidebar || !overlay) return;

        sidebar.classList.remove("-translate-x-full");
        sidebar.classList.add("show");

        overlay.classList.remove("hidden");

        document.body.classList.add("overflow-hidden");
    }

    function closeSidebar() {

        if (!sidebar || !overlay) return;

        sidebar.classList.add("-translate-x-full");
        sidebar.classList.remove("show");

        overlay.classList.add("hidden");

        document.body.classList.remove("overflow-hidden");
    }

    if (menuBtn) {

        menuBtn.addEventListener("click", function () {

            if (sidebar && sidebar.classList.contains("show")) {
                closeSidebar();
            } else {
                openSidebar();
            }

        });

    }

    if (closeMenuBtn) {
        closeMenuBtn.addEventListener("click", closeSidebar);
    }

    if (overlay) {
        overlay.addEventListener("click", closeSidebar);
    }

    // CLOSE SIDEBAR WHEN CLICKING A LINK

    if (sidebar) {

        sidebar.querySelectorAll("a").forEach(function (link) {

            link.addEventListener("click", function () {

                if (window.innerWidth < 768) {
                    closeSidebar();
                }

            });

        });

    }

    // CLOSE SIDEBAR USING ESCAPE

    document.addEventListener("keydown", function (event) {

        if (event.key === "Escape") {
            closeSidebar();
        }

    });

    // CLOSE SIDEBAR WHEN SCREEN BECOMES DESKTOP

    window.addEventListener("resize", function () {

        if (window.innerWidth >= 768) {
            closeSidebar();
        }

    });


    // ============================================
    // PROFILE UPDATE CONFIRMATION
    // ============================================

    const profileButton = document.querySelector(
        'button[name="update_profile"]'
    );

    const profileForm = profileButton
        ? profileButton.closest("form")
        : null;

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

            if (!newPassword || !confirmPassword) return;

            if (newPassword.value !== confirmPassword.value) {

                event.preventDefault();

                alert("New password and confirm password do not match!");

                confirmPassword.focus();

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

            this.setAttribute(
                "aria-label",
                show ? "Hide password" : "Show password"
            );

        });

    });


    // ============================================
    // LOGIN PASSWORD TOGGLE
    // ============================================

    const loginToggle = document.getElementById("togglePassword");
    const loginPassword = document.getElementById("password");

    if (loginToggle && loginPassword) {

        loginToggle.addEventListener("click", function () {

            const show = loginPassword.type === "password";

            loginPassword.type = show ? "text" : "password";

            loginToggle.innerHTML = show
                ? '<i data-lucide="eye-off" class="w-5 h-5"></i>'
                : '<i data-lucide="eye" class="w-5 h-5"></i>';

            loginToggle.setAttribute(
                "aria-label",
                show ? "Hide password" : "Show password"
            );

            initializeIcons();

        });

    }


    // ============================================
    // GENERAL PASSWORD TOGGLE FUNCTION
    // ============================================

    window.togglePassword = function (inputId, iconId) {

        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);

        if (!input || !icon) return;

        const show = input.type === "password";

        input.type = show ? "text" : "password";

        icon.setAttribute(
            "data-lucide",
            show ? "eye-off" : "eye"
        );

        initializeIcons();

    };


});
if (window.lucide) {
    lucide.createIcons();
}

// SHOW / HIDE PASSWORD
function togglePassword(inputId, iconId) {

    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);

    if (!input || !icon) {
        return;
    }

    const showPassword = input.type === "password";

    input.type = showPassword ? "text" : "password";

    icon.setAttribute(
        "data-lucide",
        showPassword ? "eye-off" : "eye"
    );

    if (window.lucide) {
        lucide.createIcons();
    }
}