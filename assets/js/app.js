const metaThemeColor = document.getElementById("meta-theme-color");

function applyTheme(theme) {
    if (theme === "dark") {
        document.documentElement.setAttribute("data-theme", "dark");
        if (metaThemeColor) metaThemeColor.setAttribute("content", "#0a192f");
    } else {
        document.documentElement.removeAttribute("data-theme");
        if (metaThemeColor) metaThemeColor.setAttribute("content", "#007bff");
    }
}

function initThemeToggle() {
    const savedTheme = localStorage.getItem("theme");
    if (savedTheme) {
        applyTheme(savedTheme);
    }

    const themeToggleBtn = document.getElementById("theme-toggle");
    if (!themeToggleBtn) {
        return;
    }

    themeToggleBtn.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        const isDark = document.documentElement.getAttribute("data-theme") === "dark";
        const newTheme = isDark ? "light" : "dark";
        localStorage.setItem("theme", newTheme);
        applyTheme(newTheme);
    });
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initThemeToggle);
} else {
    initThemeToggle();
}

document.addEventListener("DOMContentLoaded", () => {
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
    // EINSTELLUNGEN & LOGOUT LOGIK
    // =========================================
    window.saveSettings = async function(event, formType) {
        event.preventDefault();
        
        const btn = event.target.querySelector('button[type="submit"]');
        const originalText = btn.textContent;
        btn.textContent = "Speichert...";
        btn.disabled = true;
        
        const formData = new FormData();
        formData.append('form_type', formType);
        
        if (formType === 'profile') {
            formData.append('alias', document.getElementById('alias-input').value);
        } else if (formType === 'email') {
            formData.append('email', document.getElementById('email-input').value);
            formData.append('notifications', document.getElementById('reminder-toggle').checked);
        } else if (formType === 'password') {
            formData.append('pwd_old', document.getElementById('pwd-old').value);
            formData.append('pwd_new', document.getElementById('pwd-new').value);
        }

        try {
            const response = await fetch('api/settings_update.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.success) {
                btn.textContent = "Gespeichert ✔";
                btn.style.backgroundColor = "#2ed573";
                btn.style.borderColor = "#2ed573";
                if (formType === 'password') {
                    document.getElementById('pwd-old').value = '';
                    document.getElementById('pwd-new').value = '';
                }
            } else {
                alert(result.message);
                btn.textContent = "Fehler!";
                btn.style.backgroundColor = "var(--danger-color)";
            }
        } catch(e) {
            alert("Netzwerkfehler.");
            btn.textContent = "Fehler!";
        }
        
        setTimeout(() => {
            btn.textContent = originalText;
            btn.disabled = false;
            if(formType === 'password') {
                btn.style.backgroundColor = "var(--danger-color)";
                btn.style.borderColor = "var(--danger-color)";
            } else {
                btn.style.backgroundColor = "";
                btn.style.borderColor = "";
            }
        }, 2000);
    };

    // LOGOUT FUNKTION
    window.logout = async function() {
        if(confirm("Möchtest du dich wirklich abmelden?")) {
            // Wir rufen die auth.php mit der action 'logout' auf
            const formData = new FormData();
            formData.append('action', 'logout');
            
            try {
                await fetch('api/auth.php', { method: 'POST', body: formData });
                window.location.href = "login.php";
            } catch(e) {
                window.location.href = "login.php"; // Fallback
            }
        }
    };
});