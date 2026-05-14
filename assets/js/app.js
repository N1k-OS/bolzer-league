document.addEventListener("DOMContentLoaded", () => {
    const themeToggleBtn = document.getElementById("theme-toggle");
    const currentTheme = localStorage.getItem("theme");
    const metaThemeColor = document.getElementById("meta-theme-color");

    function applyTheme(theme) {
        if (theme === "dark") {
            document.documentElement.setAttribute("data-theme", "dark");
            metaThemeColor.setAttribute("content", "#0a192f"); // Dunkelblau für Dark Mode
        } else {
            document.documentElement.removeAttribute("data-theme");
            metaThemeColor.setAttribute("content", "#007bff"); // Helles Blau für Light Mode
        }
    }

    if (currentTheme) {
        applyTheme(currentTheme);
    }

    if(themeToggleBtn) {
        themeToggleBtn.addEventListener("click", () => {
            let theme = document.documentElement.getAttribute("data-theme");
            let newTheme = (theme === "dark") ? "light" : "dark";
            localStorage.setItem("theme", newTheme);
            applyTheme(newTheme);
        });
    }

    // --- TAB NAVIGATION LOGIK ---
    const navButtons = document.querySelectorAll('.nav-btn[data-target]');
    const tabContents = document.querySelectorAll('.tab-content');

    navButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            // 1. Alle aktiven Zustände entfernen
            navButtons.forEach(b => b.classList.remove('active'));
            tabContents.forEach(t => t.classList.remove('active'));

            // 2. Den geklickten Button aktivieren
            btn.classList.add('active');

            // 3. Den passenden Inhaltsbereich einblenden
            const targetId = btn.getAttribute('data-target');
            document.getElementById(targetId).classList.add('active');
        });
    });
});