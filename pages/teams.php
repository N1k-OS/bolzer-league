<div class="page-header">
    <h2>Teams & Kader</h2>
    <p>Die Aufstellungen für das aktuelle Event.</p>
</div>

<div class="accordion-container" id="teams-accordion">
    <?php
    // 1. Datenbank-Verbindung (Wir brauchen sie ab jetzt auf jeder Seite)
    require_once __DIR__ . '/../includes/bootstrap.php';
    $database = new Database();
    $db = $database->getConnection();

    try {
        // 2. Das aktuell laufende Event suchen
        $event_stmt = $db->query("SELECT id FROM events WHERE status = 'active' LIMIT 1");
        $current_event = $event_stmt->fetch();

        if (!$current_event) {
            echo "<p style='color: gray; text-align: center; padding: 20px;'>Aktuell läuft kein Event. Der Admin muss ein neues Turnier starten.</p>";
        } else {
            $event_id = $current_event['id'];

            // 3. Alle Teams für dieses Event laden
            $teams_stmt = $db->prepare("SELECT id, name, icon FROM teams WHERE event_id = ?");
            $teams_stmt->execute([$event_id]);
            $teams = $teams_stmt->fetchAll();

            // 4. Schleife über alle Teams
            foreach ($teams as $team) {
                echo '<div class="accordion-item">';
                
                // Header des Teams
                echo '<button class="accordion-header">';
                echo '  <span class="team-name">' . htmlspecialchars($team['icon'] . ' ' . $team['name']) . '</span>';
                echo '  <span class="accordion-icon">+</span>';
                echo '</button>';
                
                // Inhalt (Spieler) des Teams
                echo '<div class="accordion-content">';
                echo '  <ul class="player-list">';
                
                // 5. Kader (Roster) für GENAU dieses Team bei GENAU diesem Event laden
                // Wir JOINEN die users-Tabelle, um den echten Namen (Alias) und das Icon zu bekommen
                $roster_stmt = $db->prepare("
                    SELECT u.alias, u.icon, r.current_category 
                    FROM rosters r 
                    JOIN users u ON r.user_id = u.id 
                    WHERE r.event_id = ? AND r.team_id = ?
                ");
                $roster_stmt->execute([$event_id, $team['id']]);
                $players = $roster_stmt->fetchAll();

                if (count($players) === 0) {
                    echo '<li class="player-item" style="color: gray; font-size: 0.85rem;">Noch keine Spieler im Kader.</li>';
                }

                // 6. Schleife über alle Spieler dieses Teams
                foreach ($players as $player) {
                    // Falls der User kein Custom-Icon hat, nehmen wir den ersten Buchstaben vom Alias
                    $initial = empty($player['icon']) ? mb_substr($player['alias'], 0, 1) : $player['icon'];
                    $cat_class = 'category-' . $player['current_category']; 
                    $cat_label = 'Kat ' . strtoupper($player['current_category']);
                    
                    echo '    <li class="player-item">';
                    echo '      <div class="player-avatar">' . htmlspecialchars($initial) . '</div>';
                    echo '      <div class="player-info">';
                    echo '        <span class="player-name">' . htmlspecialchars($player['alias']) . '</span>';
                    echo '        <span class="player-cat ' . htmlspecialchars($cat_class) . '">' . htmlspecialchars($cat_label) . '</span>';
                    echo '      </div>';
                    echo '    </li>';
                }
                
                echo '  </ul>';
                echo '</div>'; // Ende accordion-content
                echo '</div>'; // Ende accordion-item
            }
        }
    } catch (Exception $e) {
        echo "<p class='text-danger'>Fehler beim Laden der Teams.</p>";
    }
    ?>
</div>

<!-- Admin-Button (Nur eingeblendet, wenn Admin) -->
<?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] && isset($event_id)): ?>
    <?php
    $free_users_stmt = $db->prepare("
        SELECT u.id, u.alias, u.base_category 
        FROM users u 
        LEFT JOIN rosters r ON u.id = r.user_id AND r.event_id = ?
        WHERE r.id IS NULL
    ");
    $free_users_stmt->execute([$event_id]);
    $free_users = $free_users_stmt->fetchAll();
    $free_count = count($free_users);
    $team_count = count($teams ?? []);
    ?>
    <div class="admin-actions" style="margin-top: 20px; text-align: center;">
        <button class="primary-btn" style="width: auto; padding: 10px 20px;" onclick="openDistributionModal()">Teams verteilen (Admin)</button>
    </div>

    <!-- Modal für Team Verteilung -->
    <div id="distribution-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin:0; font-size:1.2rem;">Teams einteilen</h3>
                <button class="icon-btn" onclick="closeDistributionModal()">✕</button>
            </div>
            <div class="modal-body">
                <p><strong>Status:</strong> <?= $free_count ?> Spieler haben noch kein Team. Es gibt <?= $team_count ?> Teams.</p>
                
                <?php if ($free_count > 0 && $team_count > 0): ?>
                    <button class="primary-btn u-mb-15" onclick="randomlyDistributePlayers()">Zufällige Einteilung</button>
                    
                    <hr style="margin: 20px 0; border: 0; border-top: 1px solid var(--border-color);">
                    
                    <h4 style="margin-bottom: 10px;">Manuelle Einteilung</h4>
                    <div id="manual-distribution-form" style="max-height: 250px; overflow-y: auto; padding-right: 5px;">
                        <?php foreach ($free_users as $fu): ?>
                            <div class="form-group" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <span style="font-size: 0.9rem;"><?= htmlspecialchars($fu['alias']) ?> (<?= strtoupper($fu['base_category']) ?>)</span>
                                <select class="form-select manual-team-select" data-user-id="<?= $fu['id'] ?>" style="width: 140px; padding: 6px;">
                                    <option value="0">-</option>
                                    <?php foreach ($teams as $t): ?>
                                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="primary-btn success-btn u-mt-10" onclick="manualDistributePlayers()">Speichern</button>
                <?php elseif ($team_count == 0): ?>
                    <p class="text-danger">Es gibt keine Teams für dieses Event.</p>
                <?php else: ?>
                    <p class="text-success">Alle Spieler sind bereits in Teams eingeteilt!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>