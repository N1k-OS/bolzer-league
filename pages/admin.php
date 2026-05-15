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
// ... (Die openAdminModal und closeAdminModal Funktionen bleiben gleich) ...

function openAdminModal(actionType) {
    const title = document.getElementById('admin-modal-title');
    const body = document.getElementById('admin-modal-body');
    const submitBtn = document.getElementById('admin-submit-btn');
    
    if (actionType === 'event') {
        title.textContent = "Neues Event erstellen";
        body.innerHTML = `
            <div class="form-group">
                <label>Event Name</label>
                <input type="text" id="new-event-name" class="form-input" placeholder="z.B. Sommer-Turnier">
            </div>
            <div class="form-group">
                <label>Dauer / Modus</label>
                <select id="new-event-duration" class="form-select">
                    <option value="kurz">Kurz (Jeder 1x gegeneinander)</option>
                    <option value="standard">Standard (Hin- und Rückrunde)</option>
                </select>
            </div>
            <p style="font-size:0.8rem; color:gray;">Hinweis: Das alte Event wird dabei archiviert.</p>
        `;
        submitBtn.onclick = createEvent;
    } else if (actionType === 'result') {
        title.textContent = "Ergebnis eintragen";
        body.innerHTML = `<p>Hier kommt später ein Dropdown mit allen offenen Spielen hin.</p>`;
        submitBtn.onclick = function() { alert('Noch nicht implementiert'); closeAdminModal(); };
    }
    
    document.getElementById('admin-modal').style.display = 'flex';
}

function closeAdminModal() {
    document.getElementById('admin-modal').style.display = 'none';
}

function createEvent() {
    alert("Noch nicht implementiert.");
    closeAdminModal();
}

// HIER IST DIE NEUE FUNKTION!
async function generateMatchplan() {
    if(confirm("Möchtest du jetzt den Spielplan für das aktive Event generieren? (Alte Spiele dieses Events werden gelöscht!)")) {
        
        // Wir sperren den Button kurz, damit man nicht 5x draufklickt
        const formData = new FormData();
        formData.append('action', 'generate_matchplan');

        try {
            const response = await fetch('api/admin.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if(result.success) {
                alert(result.message);
                // Laden wir die Seite neu, damit wir die neuen Matches direkt sehen können
                window.location.href = "index.php?page=matches";
            } else {
                alert("Fehler: " + result.message);
            }
        } catch(e) {
            alert("Server-Fehler. Bitte prüfen.");
        }
    }
}

function endCurrentEvent() {
    if(confirm("Bist du sicher? Dies beendet das aktuelle Turnier unwiderruflich.")) {
        alert("Noch nicht implementiert.");
    }
}
</script>