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
        // 1. Ansichten umschalten
        document.getElementById('market-view').style.display = (tabId === 'market') ? 'block' : 'none';
        document.getElementById('requests-view').style.display = (tabId === 'requests') ? 'block' : 'none';
        
        // 2. Button-Styles anpassen (sicherer Weg)
        const btns = document.querySelectorAll('.tab-switcher .tab-btn');
        btns.forEach(btn => btn.classList.remove('active'));
        
        // Wir suchen den Button, der geklickt wurde, anhand des onclick-Attributs
        const clickedBtn = document.querySelector(`.tab-switcher .tab-btn[onclick*="${tabId}"]`);
        if (clickedBtn) {
            clickedBtn.classList.add('active');
        }
    }

    let currentTargetPrice = 0;

    function openTradeModal(playerName, price) {
        document.getElementById('modal-target-player').textContent = playerName;
        document.getElementById('modal-target-price').textContent = price;
        currentTargetPrice = price;
        
        // Modal anzeigen & Reset
        document.getElementById('trade-offer-player').value = "";
        document.getElementById('trade-calculation').style.display = 'none';
        document.getElementById('submit-trade-btn').disabled = true;
        document.getElementById('trade-modal').style.display = 'flex';
    }

    function closeTradeModal() {
        document.getElementById('trade-modal').style.display = 'none';
    }

    function calculateTrade() {
        const select = document.getElementById('trade-offer-player');
        const myPlayerPrice = parseInt(select.value);
        
        if (!myPlayerPrice) return;
        
        // Die Mathematik: Was kostet der Spieler - was ist mein Spieler wert?
        const cost = currentTargetPrice - myPlayerPrice;
        const myBudget = parseInt(document.getElementById('current-budget').value);
        
        const calcBox = document.getElementById('trade-calculation');
        const costSpan = document.getElementById('trade-cost');
        const warning = document.getElementById('trade-warning');
        const submitBtn = document.getElementById('submit-trade-btn');
        
        calcBox.style.display = 'block';
        
        // Wenn cost positiv ist, musst du zahlen. Wenn negativ, bekommst du Geld.
        if (cost > 0) {
            costSpan.textContent = cost;
            costSpan.className = "font-bold text-danger";
        } else {
            costSpan.textContent = cost + " (Du erhältst Coins)";
            costSpan.className = "font-bold text-success";
        }
        
        // Budget-Check
        if (cost > myBudget) {
            warning.style.display = 'block';
            submitBtn.disabled = true;
        } else {
            warning.style.display = 'none';
            submitBtn.disabled = false;
        }
    }

    function sendTradeRequest() {
        alert("Anfrage wurde erfolgreich an das andere Team gesendet!");
        closeTradeModal();
    }

    function acceptTrade(id) {
        alert("Anfrage " + id + " angenommen! (Backend-Verbindung fehlt noch)");
    }

    function declineTrade(id) {
        alert("Anfrage " + id + " abgelehnt.");
    }
});