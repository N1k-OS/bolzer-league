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

    // =========================================
    // AKKORDEON LOGIK (Teams)
    // =========================================
    const accordions = document.querySelectorAll(".accordion-header");

    accordions.forEach(acc => {
        acc.addEventListener("click", function() {
            // Das Plus/Minus Icon finden
            const icon = this.querySelector(".accordion-icon");
            // Den Inhalt unter dem angeklickten Header finden
            const content = this.nextElementSibling;
            
            // Wenn es schon offen ist -> zumachen
            if (content.style.maxHeight) {
                content.style.maxHeight = null;
                icon.textContent = "+";
            } 
            // Wenn es zu ist -> aufmachen
            else {
                // (Optional) Andere offene Tabs vorher schließen:
                /*
                accordions.forEach(otherAcc => {
                    otherAcc.nextElementSibling.style.maxHeight = null;
                    otherAcc.querySelector(".accordion-icon").textContent = "+";
                });
                */
                
                // Setzt die Höhe genau auf die Größe des Inhalts
                content.style.maxHeight = content.scrollHeight + "px";
                icon.textContent = "−";
            }
        });
    });
});