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
function openSidebar() {

    document.getElementById("adminSidebar")
        .classList.remove("-translate-x-full");

    document.getElementById("overlay")
        .classList.remove("hidden");

    document.body.classList.add("overflow-hidden");
}

function closeSidebar() {

    document.getElementById("adminSidebar")
        .classList.add("-translate-x-full");

    document.getElementById("overlay")
        .classList.add("hidden");

    document.body.classList.remove("overflow-hidden");
}


// RESIDENT SEARCH

const searchInput = document.getElementById("residentSearch");

if (searchInput) {

    searchInput.addEventListener("input", function () {

        const searchValue = this.value.toLowerCase().trim();

        const rows = document.querySelectorAll(".resident-row");

        let visibleCount = 0;

        rows.forEach(row => {

            const rowText = row.textContent.toLowerCase();

            if (rowText.includes(searchValue)) {

                row.style.display = "";
                visibleCount++;

            } else {

                row.style.display = "none";

            }

        });

        const noResults = document.getElementById("noSearchResults");

        if (noResults) {
            noResults.classList.toggle("hidden", visibleCount !== 0);
        }

    });

}


// CLOSE SIDEBAR WHEN NAVIGATING ON MOBILE

document.querySelectorAll("#adminSidebar .nav-link").forEach(link => {

    link.addEventListener("click", function () {

        if (window.innerWidth < 1024) {
            closeSidebar();
        }

    });

});


// CLOSE SIDEBAR WHEN RESIZING TO DESKTOP

window.addEventListener("resize", function () {

    if (window.innerWidth >= 1024) {
        closeSidebar();
    }

});