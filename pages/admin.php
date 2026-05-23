<?php
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    echo "<div class='alert-box text-danger'>Zugriff verweigert.</div>";
    return; 
}
?>

<div class="page-header">
    <h2>Admin Dashboard</h2>
    <p>Turnierleitung und Spielverwaltung.</p>
</div>

<div class="card-container settings-card">
    <h3 class="settings-title">🏆 Event-Steuerung</h3>
    <p class="u-text-muted u-mb-12">
        Spielplan und Turnierbaum aktualisieren sich bei Ergebniseintrag automatisch (später per Umfrage).
        Die Knöpfe unten sind nur Fallback bei Datenproblemen.
    </p>
    <div class="u-flex-col">
        <button class="primary-btn" onclick="openAdminModal('event')">Neues Event erstellen</button>
        <button class="primary-btn btn--sidebar" onclick="generateMatchplan()">Fallback: Spielplan neu aufbauen</button>
        <button class="primary-btn btn--sidebar" onclick="generateWMKO()">Gruppenphase beenden & K.O.-Baum erstellen (WM-Modus)</button>
        <button class="primary-btn btn--warning" onclick="calculateNextRound()">Fallback: K.O.-Bracket reparieren</button>
        <button class="primary-btn danger-btn" onclick="endCurrentEvent()">Fallback: Event beenden</button>
    </div>
</div>

<div class="card-container settings-card">
    <h3 class="settings-title">⚔️ Spielverwaltung</h3>
    <p class="u-text-muted u-mb-15">Manuelle Korrekturen, bis Umfragen Tore/MVP automatisch liefern.</p>
    
    <div class="u-flex-col">
        <button class="primary-btn success-btn" onclick="openAdminModal('result')">Fallback: Ergebnis manuell eintragen</button>
        <button class="primary-btn btn--sidebar" onclick="openAdminModal('transfer')">Transfer erzwingen (Override)</button>
    </div>
</div>


<!-- DAS ADMIN-MODAL (Universell für Formulare) -->
<div id="admin-modal" class="modal-overlay">
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
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

async function openAdminModal(actionType) {
    const title = document.getElementById('admin-modal-title');
    const body = document.getElementById('admin-modal-body');
    const submitBtn = document.getElementById('admin-submit-btn');
    const modal = document.getElementById('admin-modal');

    submitBtn.classList.remove('u-hidden');

    if (actionType === 'event') {
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
                    <option value="standard">Liga - Standard (Punkte & Elimination)</option>
                    <option value="lang">Liga - Erweitert (Hin- und Rückrunde)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Anzahl der Teams</label>
                <input type="number" id="new-event-teams" class="form-input" value="4" min="4" max="16" step="4">
                <small class="u-text-muted">Elimination: nur 4, 8 oder 16 Teams.</small>
            </div>
        `;
        submitBtn.onclick = createEvent;
        modal.classList.add('is-open');
        return;
    }

    if (actionType === 'result') {
        title.textContent = "Lade Spiele...";
        body.innerHTML = "<p>Bitte warten...</p>";
        modal.classList.add('is-open');

        try {
            const response = await fetch('api/get_matches.php');
            const matches = await response.json();

            title.textContent = "Ergebnis eintragen";

            if (matches.length === 0) {
                body.innerHTML = "<p class='text-danger'>Es gibt keine offenen Spiele, bei denen beide Teams feststehen.</p>";
                submitBtn.classList.add('u-hidden');
            } else {
                const options = matches.map(m =>
                    `<option value="${m.id}">Tag ${m.matchday_number} | ${escapeHtml(m.team1)} vs ${escapeHtml(m.team2)}</option>`
                ).join('');
                body.innerHTML = `
                    <div class="form-group">
                        <label>Welches Spiel?</label>
                        <select id="result-match-id" class="form-select">${options}</select>
                    </div>
                    <div class="form-group u-flex-row-center">
                        <input type="number" id="result-score1" class="form-input u-input-score" placeholder="0" min="0">
                        <span> : </span>
                        <input type="number" id="result-score2" class="form-input u-input-score" placeholder="0" min="0">
                    </div>
                `;
                submitBtn.classList.remove('u-hidden');
                submitBtn.onclick = submitResult;
            }
        } catch (e) {
            body.innerHTML = "<p class='text-danger'>Fehler beim Laden der Spiele.</p>";
            submitBtn.classList.add('u-hidden');
        }
        return;
    }

    if (actionType === 'transfer') {
        title.textContent = "Lade Kader...";
        body.innerHTML = "<p>Bitte warten...</p>";
        modal.classList.add('is-open');

        try {
            const response = await fetch('api/get_admin_roster_overview.php');
            const data = await response.json();

            title.textContent = "Transfer erzwingen (Override)";

            if (!data.success || !data.players || data.players.length === 0) {
                body.innerHTML = "<p class='text-danger'>" + escapeHtml(data.message || 'Keine Spieler im Kader oder kein aktives Event.') + "</p>";
                submitBtn.classList.add('u-hidden');
                return;
            }

            const playerOpts = data.players.map(p =>
                `<option value="${p.user_id}">${escapeHtml(p.name)} (${escapeHtml(p.team_name)})</option>`
            ).join('');
            const teamOpts = data.teams.map(t =>
                `<option value="${t.id}">${escapeHtml(t.name)}</option>`
            ).join('');

            body.innerHTML = `
                <p class="u-text-muted u-mb-12">Verschiebt einen Spieler ohne Tauschlogik in ein anderes Team dieses Events.</p>
                <div class="form-group">
                    <label>Spieler</label>
                    <select id="transfer-user-id" class="form-select">${playerOpts}</select>
                </div>
                <div class="form-group">
                    <label>Zielteam</label>
                    <select id="transfer-target-team-id" class="form-select">${teamOpts}</select>
                </div>
            `;
            submitBtn.classList.remove('u-hidden');
            submitBtn.onclick = submitForceTransfer;
        } catch (e) {
            body.innerHTML = "<p class='text-danger'>Fehler beim Laden der Kaderdaten.</p>";
            submitBtn.classList.add('u-hidden');
        }
    }
}

function closeAdminModal() {
    document.getElementById('admin-modal').classList.remove('is-open');
}

async function createEvent() {
    const name = document.getElementById('new-event-name').value;
    const duration = document.getElementById('new-event-duration').value;
    const teamsCount = document.getElementById('new-event-teams').value;

    if (!name) {
        alert("Bitte Namen eingeben.");
        return;
    }

    const teams = parseInt(teamsCount, 10);
    if (duration === 'kurz' && ![4, 8, 16].includes(teams)) {
        alert("Elimination (K.O.): Bitte genau 4, 8 oder 16 Teams wählen.");
        return;
    }

    const formData = new FormData();
    formData.append('action', 'create_event');
    formData.append('name', name);
    formData.append('duration', duration);
    formData.append('teams_count', teamsCount);

    try {
        const response = await fetch('api/admin.php', { method: 'POST', body: formData });
        const result = await response.json();

        alert(result.message);
        if (result.success) {
            window.location.reload();
        }
    } catch (e) {
        alert("Server-Fehler.");
    }
}

async function generateMatchplan() {
    if (!confirm('Spielplan komplett neu aufbauen? Alle bisherigen Spieltage und Ergebnisse dieses Events werden gelöscht.')) {
        return;
    }
    const formData = new FormData();
    formData.append('action', 'generate_matchplan');

    try {
        const response = await fetch('api/admin.php', { method: 'POST', body: formData });
        const result = await response.json();

        alert(result.message);
        if (result.success) {
            window.location.href = "index.php?page=matches";
        }
    } catch (e) {
        alert("Server-Fehler.");
    }
}

async function generateWMKO() {
    if (!confirm('Gruppenphase beenden und K.O.-Runde für die besten Teams generieren?')) {
        return;
    }
    const formData = new FormData();
    formData.append('action', 'generate_wm_ko');

    try {
        const response = await fetch('api/admin.php', { method: 'POST', body: formData });
        const result = await response.json();

        alert(result.message);
        if (result.success) {
            window.location.href = "index.php?page=bracket";
        }
    } catch (e) {
        alert("Server-Fehler.");
    }
}

async function calculateNextRound() {
    if (!confirm("K.O.-Bracket aus allen abgeschlossenen Spielen neu synchronisieren? (Nur bei Datenproblemen nötig.)")) {
        return;
    }
    const formData = new FormData();
    formData.append('action', 'calculate_next_round');

    try {
        const response = await fetch('api/admin.php', { method: 'POST', body: formData });
        const result = await response.json();

        alert(result.message);
        if (result.success) {
            window.location.href = "index.php?page=matches";
        }
    } catch (e) {
        alert("Server-Fehler.");
    }
}

async function endCurrentEvent() {
    if (!confirm("Aktives Event wirklich beenden? Es gibt danach kein laufendes Event mehr, bis du ein neues anlegst.")) {
        return;
    }
    const formData = new FormData();
    formData.append('action', 'end_event');

    try {
        const response = await fetch('api/admin.php', { method: 'POST', body: formData });
        const result = await response.json();

        alert(result.message);
        if (result.success) {
            window.location.reload();
        }
    } catch (e) {
        alert("Server-Fehler.");
    }
}

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

        if (result.success) {
            alert(result.message);
            window.location.href = "index.php?page=matches";
        } else {
            alert("Fehler: " + result.message);
        }
    } catch (e) {
        alert("Server-Fehler.");
    }
}

async function submitForceTransfer() {
    const userId = document.getElementById('transfer-user-id').value;
    const targetTeamId = document.getElementById('transfer-target-team-id').value;

    if (!userId || !targetTeamId) {
        alert('Bitte Spieler und Zielteam wählen.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'force_transfer');
    formData.append('user_id', userId);
    formData.append('target_team_id', targetTeamId);

    try {
        const response = await fetch('api/admin.php', { method: 'POST', body: formData });
        const result = await response.json();

        if (result.success) {
            alert(result.message);
            closeAdminModal();
            window.location.href = "index.php?page=transfer";
        } else {
            alert("Fehler: " + result.message);
        }
    } catch (e) {
        alert("Server-Fehler.");
    }
}
</script>