// Warte, bis das HTML vollständig geladen ist
document.addEventListener("DOMContentLoaded", () => {
    
    // --- DARK / LIGHT MODE LOGIK ---
    const themeToggleBtn = document.getElementById("theme-toggle");
    const currentTheme = localStorage.getItem("theme");

    // Prüfen, ob der Nutzer beim letzten Mal Dark Mode aktiviert hatte
    if (currentTheme === "dark") {
        document.documentElement.setAttribute("data-theme", "dark");
    }

    // Beim Klick auf den Theme-Button
    themeToggleBtn.addEventListener("click", () => {
        let theme = document.documentElement.getAttribute("data-theme");
        
        if (theme === "dark") {
            document.documentElement.removeAttribute("data-theme");
            localStorage.setItem("theme", "light");
        } else {
            document.documentElement.setAttribute("data-theme", "dark");
            localStorage.setItem("theme", "dark");
        }
    });

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