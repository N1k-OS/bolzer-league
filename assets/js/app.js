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

    // Hier kommt später mehr Logik hin (z.B. für das Zahnrad-Menü)
});