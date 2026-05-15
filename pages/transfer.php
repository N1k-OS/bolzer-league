<div class="page-header flex-align-center" style="justify-content: space-between;">
    <div>
        <h2>Transfermarkt</h2>
        <p>Spieler tauschen und Kader planen.</p>
    </div>
    
    <?php
    require_once 'includes/db.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $user_id = $_SESSION['user_id']; 
    
    $my_team_budget = 0;
    $my_team_id = null;
    $my_role = 'player';
    $market_players = [];
    $my_players = [];

    try {
        $event_stmt = $db->query("SELECT id FROM events WHERE status = 'active' LIMIT 1");
        $current_event = $event_stmt->fetch();

        if ($current_event) {
            $event_id = $current_event['id'];

            $my_team_stmt = $db->prepare("
                SELECT t.id, t.budget, t.captain_user_id 
                FROM rosters r 
                JOIN teams t ON r.team_id = t.id 
                WHERE r.event_id = :event_id AND r.user_id = :user_id
            ");
            $my_team_stmt->execute([':event_id' => $event_id, ':user_id' => $user_id]);
            $my_team_data = $my_team_stmt->fetch();

            if ($my_team_data) {
                $my_team_id = $my_team_data['id'];
                $my_team_budget = $my_team_data['budget'];
                if ($my_team_data['captain_user_id'] == $user_id) {
                    $my_role = 'captain';
                }

                // FIX: u.id AS player_id hinzugefügt!
                $market_stmt = $db->prepare("
                    SELECT u.id AS player_id, u.alias AS name, u.icon, t.name AS team_name, r.current_category, r.current_price 
                    FROM rosters r
                    JOIN users u ON r.user_id = u.id
                    JOIN teams t ON r.team_id = t.id
                    WHERE r.event_id = :event_id AND r.team_id != :my_team_id
                    ORDER BY r.current_price DESC, u.alias ASC
                ");
                $market_stmt->execute([':event_id' => $event_id, ':my_team_id' => $my_team_id]);
                $market_players = $market_stmt->fetchAll();

                // FIX: u.id AS player_id hinzugefügt!
                $my_players_stmt = $db->prepare("
                    SELECT u.id AS player_id, u.alias AS name, r.current_price 
                    FROM rosters r
                    JOIN users u ON r.user_id = u.id
                    WHERE r.event_id = :event_id AND r.team_id = :my_team_id
                ");
                $my_players_stmt->execute([':event_id' => $event_id, ':my_team_id' => $my_team_id]);
                $my_players = $my_players_stmt->fetchAll();
            }
        }
    } catch (Exception $e) {
        echo "<p class='text-danger'>Fehler beim Laden des Transfermarkts.</p>";
    }
    ?>

    <div class="budget-badge">
        💰 <?php echo htmlspecialchars($my_team_budget); ?> BL-Coins
    </div>
</div>

<div class="tab-switcher">
    <button class="tab-btn active" onclick="switchMarketTab('market')">Markt</button>
    <button class="tab-btn" onclick="switchMarketTab('requests')">Anfragen</button>
</div>

<div id="market-view" class="market-section">
    <?php if (!$my_team_id): ?>
        <p style="text-align: center; color: gray; padding: 20px;">Du bist für dieses Event noch keinem Team zugewiesen.</p>
    <?php elseif (empty($market_players)): ?>
        <p style="text-align: center; color: gray; padding: 20px;">Es sind keine Spieler auf dem Markt verfügbar.</p>
    <?php else: ?>
        <div class="card-container">
            <div class="list-header">
                <div class="col-team">Spieler (Anderes Team)</div>
                <div class="col-pts" style="width: 70px;">Preis</div>
                <div style="width: 40px;"></div>
            </div>
            <ul class="clean-list">
                <?php foreach ($market_players as $player): 
                    $initial = empty($player['icon']) ? mb_substr($player['name'], 0, 1) : $player['icon'];
                    $cat_label = 'Kat ' . strtoupper($player['current_category']);
                ?>
                    <li class="list-item">
                        <div class="col-team flex-align-center">
                            <div class="avatar-circle"><?php echo htmlspecialchars($initial); ?></div>
                            <div class="flex-column">
                                <span class="font-bold"><?php echo htmlspecialchars($player['name']); ?></span>
                                <span style="font-size: 0.75rem; color: gray;">
                                    <?php echo htmlspecialchars($player['team_name']); ?> | <?php echo htmlspecialchars($cat_label); ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-pts font-bold">
                            <?php echo htmlspecialchars($player['current_price']); ?>
                        </div>
                        <div style="width: 40px; text-align: right;">
                            <button class="icon-btn color-primary" onclick="openTradeModal(<?php echo $player['player_id']; ?>, '<?php echo htmlspecialchars($player['name'], ENT_QUOTES); ?>', <?php echo $player['current_price']; ?>)">🔄</button>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<div id="requests-view" class="market-section" style="display: none;">
    <?php if ($my_role !== 'captain'): ?>
        <div class="alert-box">Nur der Team-Captain kann Anfragen bearbeiten.</div>
    <?php else: ?>
        <p style="padding: 20px; text-align: center; color: gray;">Hier erscheinen bald die eingehenden Tauschanfragen.</p>
    <?php endif; ?>
</div>

<div id="trade-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Tauschanfrage erstellen</h3>
            <button class="icon-btn" onclick="closeTradeModal()">❌</button>
        </div>
        
        <div class="modal-body">
            <p>Du möchtest <strong id="modal-target-player" class="color-primary">Spieler X</strong> verpflichten.</p>
            <p>Sein Marktwert: <strong id="modal-target-price">0</strong> Coins.</p>
            
            <hr style="margin: 15px 0; border: 0; border-top: 1px solid var(--list-divider);">
            
            <div class="form-group">
                <label for="trade-offer-player">Wen bietest du im Tausch an?</label>
                <select id="trade-offer-player" class="form-select" onchange="calculateTrade()">
                    <option value="" disabled selected>-- Tauschspieler zwingend wählen --</option>
                    <?php foreach ($my_players as $my_player): ?>
                        <option value="<?php echo $my_player['player_id']; ?>" data-price="<?php echo $my_player['current_price']; ?>">
                            <?php echo htmlspecialchars($my_player['name']); ?> (Wert: <?php echo htmlspecialchars($my_player['current_price']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="trade-calculation" class="trade-calc-box" style="display: none;">
                <strong>Kostenübersicht:</strong><br>
                Differenz: <span id="trade-cost" class="font-bold">0</span><br>
                <small id="trade-warning" class="text-danger" style="display:none;">Nicht genug Budget!</small>
            </div>
        </div>
        
        <div class="modal-footer">
            <button class="primary-btn" id="submit-trade-btn" disabled onclick="sendTradeRequest()">Anfrage senden</button>
        </div>
    </div>
</div>

<input type="hidden" id="current-budget" value="<?php echo htmlspecialchars($my_team_budget); ?>">