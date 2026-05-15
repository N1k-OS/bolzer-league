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
    // AKKORDEON LOGIK (Event Delegation für dynamische Inhalte)
    // =========================================
    document.addEventListener("click", function(event) {
        // Prüfen, ob das geklickte Element ein accordion-header ist (oder ein Kind davon)
        const header = event.target.closest(".accordion-header");
        
        if (header) {
            const icon = header.querySelector(".accordion-icon");
            const content = header.nextElementSibling;
            
            if (content.style.maxHeight) {
                // Schließen
                content.style.maxHeight = null;
                if(icon) icon.textContent = "+";
            } else {
                // Öffnen
                content.style.maxHeight = content.scrollHeight + "px";
                if(icon) icon.textContent = "−";
            }
        }
    });

    // =========================================
    // TRANSFERMARKT LOGIK
    // =========================================
    function switchMarketTab(tabId) {
        // Ansichten umschalten
        document.getElementById('market-view').style.display = (tabId === 'market') ? 'block' : 'none';
        document.getElementById('requests-view').style.display = (tabId === 'requests') ? 'block' : 'none';
        
        // Button-Styles anpassen
        const btns = document.querySelectorAll('.tab-switcher .tab-btn');
        btns.forEach(btn => btn.classList.remove('active'));
        event.currentTarget.classList.add('active');
    }

    // Dummy-Funktionen für die Buttons
    function openTradeModal(playerName, price) {
        alert("Hier öffnet sich später das Formular, um " + playerName + " (Wert: " + price + ") ein Tauschangebot zu machen.");
    }

    function acceptTrade(id) {
        alert("Anfrage " + id + " angenommen! (Backend-Verbindung fehlt noch)");
    }

    function declineTrade(id) {
        alert("Anfrage " + id + " abgelehnt.");
    }
});