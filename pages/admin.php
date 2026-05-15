<?php
// Sicherheits-Check: Nur Admins dürfen diese Datei aufrufen!
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    echo "<div class='alert-box text-danger'>Zugriff verweigert.</div>";
    return; // Bricht das Einbinden hier ab
}
?>

<div class="page-header">
    <h2>Admin Dashboard</h2>
    <p>Turnierleitung und Spielverwaltung.</p>
</div>

<div class="card-container settings-card">
    <h3 class="settings-title">🏆 Event-Steuerung</h3>
    
    <div style="display: flex; flex-direction: column; gap: 15px;">
        <button class="primary-btn" onclick="openAdminModal('event')">Neues Event erstellen</button>
        
        <!-- Button zum Erstellen des Spielplans (Matches generieren) -->
        <button class="primary-btn" style="background-color: var(--sidebar-active);" onclick="generateMatchplan()">Spielplan generieren</button>
        
        <button class="primary-btn danger-btn" onclick="endCurrentEvent()">Aktuelles Event beenden</button>
    </div>
</div>

<div class="card-container settings-card">
    <h3 class="settings-title">⚔️ Spielverwaltung</h3>
    <p style="font-size: 0.85rem; color: gray; margin-bottom: 15px;">Hier markierst du Spiele als beendet oder überschreibst Ergebnisse.</p>
    
    <div style="display: flex; flex-direction: column; gap: 15px;">
        <button class="primary-btn" style="background-color: #2ed573;" onclick="openAdminModal('result')">Ergebnis manuell eintragen</button>
        <button class="primary-btn" style="background-color: var(--sidebar-active);" onclick="openAdminModal('transfer')">Transfer erzwingen (Override)</button>
    </div>
</div>


<!-- DAS ADMIN-MODAL (Universell für Formulare) -->
<div id="admin-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="admin-modal-title">Aktion</h3>
            <button class="icon-btn" onclick="closeAdminModal()">❌</button>
        </div>
        
        <div class="modal-body" id="admin-modal-body">
            <!-- Formular wird per JS injiziert -->
        </div>
        
        <div class="modal-footer">
            <button class="primary-btn" id="admin-submit-btn">Ausführen</button>
        </div>
    </div>
</div>

<script>
async function openAdminModal(actionType) {
    const title = document.getElementById('admin-modal-title');
    const body = document.getElementById('admin-modal-body');
    const submitBtn = document.getElementById('admin-submit-btn');
    
    if (actionType === 'event') {
        // ... (Dein bisheriger Code für Event erstellen bleibt gleich)
        title.textContent = "Neues Event erstellen";
        body.innerHTML = `
            <div class="form-group">
                <label>Event Name</label>
                <input type="text" id="new-event-name" class="form-input" placeholder="z.B. Sommer-Turnier">
            </div>
            <div class="form-group">
                <label>Modus (Turnier-Art)</label>
                <select id="new-event-duration" class="form-select">
                    <option value="kurz">Elimination (K.O. System für 4, 8, 16 Teams)</option>
                    <option value="standard">Liga - Einfach (Jeder 1x gegen Jeden)</option>
                    <option value="lang">Liga - Erweitert (Hin- und Rückrunde)</option>
                </select>
            </div>
        `;
        submitBtn.onclick = createEvent;
    } 
    
    // HIER IST DAS NEUE ERGEBNIS-FORMULAR
    else if (actionType === 'result') {
        title.textContent = "Lade Spiele...";
        body.innerHTML = "<p>Bitte warten...</p>";
        document.getElementById('admin-modal').style.display = 'flex';

        try {
            // Holt alle offenen Spiele aus der Datenbank
            const response = await fetch('api/get_matches.php');
            const matches = await response.json();

            title.textContent = "Ergebnis eintragen";
            
            if (matches.length === 0) {
                body.innerHTML = "<p class='text-danger'>Es gibt keine offenen Spiele, bei denen beide Teams feststehen.</p>";
                submitBtn.style.display = 'none';
            } else {
                let options = matches.map(m => `<option value="${m.id}">Tag ${m.matchday_number} | ${m.team1} vs ${m.team2}</option>`).join('');
                
                body.innerHTML = `
                    <div class="form-group">
                        <label>Welches Spiel?</label>
                        <select id="result-match-id" class="form-select">${options}</select>
                    </div>
                    <div class="form-group" style="display: flex; gap: 10px; align-items: center;">
                        <input type="number" id="result-score1" class="form-input" style="width: 60px; text-align: center;" placeholder="0" min="0">
                        <span> : </span>
                        <input type="number" id="result-score2" class="form-input" style="width: 60px; text-align: center;" placeholder="0" min="0">
                    </div>
                `;
                submitBtn.style.display = 'block';
                submitBtn.onclick = submitResult;
            }
        } catch(e) {
            body.innerHTML = "<p class='text-danger'>Fehler beim Laden der Spiele.</p>";
        }
    }
    
    document.getElementById('admin-modal').style.display = 'flex';
}

function closeAdminModal() {
    document.getElementById('admin-modal').style.display = 'none';
}

function createEvent() { alert("Noch nicht implementiert."); closeAdminModal(); }
async function generateMatchplan() { /* ... Dein alter Code bleibt ... */ }

// NEUE FUNKTION: ERGEBNIS AN DAS BACKEND SENDEN
async function submitResult() {
    const matchId = document.getElementById('result-match-id').value;
    const s1 = document.getElementById('result-score1').value || 0;
    const s2 = document.getElementById('result-score2').value || 0;

    const formData = new FormData();
    formData.append('action', 'submit_result');
    formData.append('match_id', matchId);
    formData.append('score1', s1);
    formData.append('score2', s2);

    try {
        const response = await fetch('api/admin.php', { method: 'POST', body: formData });
        const result = await response.json();
        
        if(result.success) {
            alert(result.message);
            window.location.href = "index.php?page=matches";
        } else {
            alert("Fehler: " + result.message);
        }
    } catch(e) {
        alert("Server-Fehler.");
    }
}
</script>