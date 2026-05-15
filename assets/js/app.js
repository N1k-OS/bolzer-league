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
    // Mach diese Funktionen global verfügbar, falls sie inline im HTML aufgerufen werden
    window.switchMarketTab = function(tabId) {
        const marketView = document.getElementById('market-view');
        const requestsView = document.getElementById('requests-view');
        
        if (!marketView || !requestsView) return; 

        marketView.style.display = (tabId === 'market') ? 'block' : 'none';
        requestsView.style.display = (tabId === 'requests') ? 'block' : 'none';
        
        const btns = document.querySelectorAll('.tab-switcher .tab-btn');
        btns.forEach(btn => btn.classList.remove('active'));
        
        const clickedBtn = document.querySelector(`.tab-switcher .tab-btn[onclick*="${tabId}"]`);
        if (clickedBtn) clickedBtn.classList.add('active');
    };

    let currentTargetPrice = 0;
    let currentTargetId = null; // Neu: Ziel-ID speichern

    window.openTradeModal = function(playerId, playerName, price) {
        currentTargetId = playerId;
        currentTargetPrice = price;
        
        document.getElementById('modal-target-player').textContent = playerName;
        document.getElementById('modal-target-price').textContent = price;
        
        // Reset Modal
        document.getElementById('trade-offer-player').value = "";
        document.getElementById('trade-calculation').style.display = 'none';
        document.getElementById('submit-trade-btn').disabled = true;
        document.getElementById('trade-modal').style.display = 'flex';
    };

    window.closeTradeModal = function() {
        document.getElementById('trade-modal').style.display = 'none';
        currentTargetId = null;
    };

    window.calculateTrade = function() {
        const select = document.getElementById('trade-offer-player');
        const selectedOption = select.options[select.selectedIndex];
        
        const calcBox = document.getElementById('trade-calculation');
        const costSpan = document.getElementById('trade-cost');
        const warning = document.getElementById('trade-warning');
        const submitBtn = document.getElementById('submit-trade-btn');
        
        // Abbruch, wenn noch der Standard-Text "-- Tauschspieler zwingend wählen --" aktiv ist
        if (!selectedOption || selectedOption.value === "" || selectedOption.disabled) {
            calcBox.style.display = 'none';
            submitBtn.disabled = true;
            return;
        }
        
        // Preis aus dem data-Attribut lesen
        const myPlayerPrice = parseInt(selectedOption.getAttribute('data-price')) || 0;
        const myBudget = parseInt(document.getElementById('current-budget').value) || 0;
        
        // Berechnung: Preis Zielspieler minus Preis meines Spielers
        const cost = currentTargetPrice - myPlayerPrice;
        
        calcBox.style.display = 'block';
        
        if (cost > 0) {
            costSpan.textContent = cost + " Coins zahlen";
            costSpan.className = "font-bold text-danger";
        } else if (cost < 0) {
            costSpan.textContent = Math.abs(cost) + " Coins erhalten";
            costSpan.className = "font-bold text-success";
        } else {
            costSpan.textContent = "0 Coins (Direkter Tausch)";
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
    };

    window.sendTradeRequest = function() {
        const select = document.getElementById('trade-offer-player');
        const offerPlayerId = select.value;
        
        // Hier würdest du später den fetch() Request ans PHP Backend machen!
        console.log("Sende an Backend: Ziel-Spieler ID:", currentTargetId, "Angebotener Spieler ID:", offerPlayerId);
        
        alert("Anfrage wurde erfolgreich vorbereitet! (Siehe Konsole)");
        closeTradeModal();
    };

    window.acceptTrade = function(id) {
        alert("Anfrage " + id + " angenommen!");
    };

    window.declineTrade = function(id) {
        alert("Anfrage " + id + " abgelehnt.");
    };

    // =========================================
    // EINSTELLUNGEN LOGIK
    // =========================================
    function saveSettings(event, formType) {
        event.preventDefault();
        
        const btn = event.target.querySelector('button[type="submit"]');
        const originalText = btn.textContent;
        
        // CSS-Klassen nutzen statt harter Farben
        btn.textContent = "Gespeichert ✔";
        btn.classList.add('success-btn');
        btn.classList.remove('danger-btn'); 
        
        setTimeout(() => {
            btn.textContent = originalText;
            btn.classList.remove('success-btn');
            
            // Wenn es der Passwort-Button war, geben wir ihm seine Gefahr-Klasse zurück
            if(formType === 'password') {
                btn.classList.add('danger-btn');
            }
        }, 2000);
    }

    function logout() {
        if(confirm("Möchtest du dich wirklich abmelden?")) {
            alert("Logout erfolgreich. (Weiterleitung fehlt noch)");
            // Später: window.location.href = "login.php";
        }
    }
});