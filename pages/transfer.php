<?php
$market_json = file_get_contents('data/market.json');
$data = json_decode($market_json, true);
?>

<div class="page-header flex-align-center" style="justify-content: space-between;">
    <div>
        <h2>Transfermarkt</h2>
        <p>Spieler tauschen und Kader planen.</p>
    </div>
    <!-- Budget-Anzeige oben rechts -->
    <div class="budget-badge">
        💰 <?php echo $data['my_team_budget']; ?> BL-Coins
    </div>
</div>

<!-- Sub-Navigation im Transfer-Tab -->
<div class="tab-switcher">
    <button class="tab-btn active" onclick="switchMarketTab('market')">Markt</button>
    <button class="tab-btn" onclick="switchMarketTab('requests')">
        Anfragen 
        <?php if(count($data['requests_in']) > 0): ?>
            <span class="notification-dot"><?php echo count($data['requests_in']); ?></span>
        <?php endif; ?>
    </button>
</div>

<!-- BEREICH 1: DER MARKT -->
<div id="market-view" class="market-section">
    <div class="card-container">
        <div class="list-header">
            <div class="col-team">Spieler (Anderes Team)</div>
            <div class="col-pts" style="width: 70px;">Preis</div>
            <div style="width: 40px;"></div>
        </div>
        <ul class="clean-list">
            <?php foreach ($data['market'] as $player): ?>
                <li class="list-item">
                    <div class="col-team flex-align-center">
                        <div class="avatar-circle"><?php echo mb_substr($player['name'], 0, 1); ?></div>
                        <div class="flex-column">
                            <span class="font-bold"><?php echo htmlspecialchars($player['name']); ?></span>
                            <span style="font-size: 0.75rem; color: gray;">
                                <?php echo htmlspecialchars($player['team']); ?> | <?php echo htmlspecialchars($player['cat_label']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-pts font-bold">
                        <?php echo $player['price']; ?>
                    </div>
                    <!-- Tauschen Button -->
                    <div style="width: 40px; text-align: right;">
                        <button class="icon-btn color-primary" onclick="openTradeModal('<?php echo $player['name']; ?>', <?php echo $player['price']; ?>)">🔄</button>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<!-- BEREICH 2: ANFRAGEN (Nur für den Captain relevant) -->
<div id="requests-view" class="market-section" style="display: none;">
    <?php if ($data['my_role'] !== 'captain'): ?>
        <div class="alert-box">Nur der Team-Captain kann Anfragen bearbeiten.</div>
    <?php else: ?>
        <?php foreach ($data['requests_in'] as $req): ?>
            <div class="trade-request-card">
                <div class="trade-header">
                    Anfrage von <strong><?php echo htmlspecialchars($req['from_team']); ?></strong>
                </div>
                <div class="trade-body">
                    Sie wollen: <strong><?php echo htmlspecialchars($req['wants_player']); ?></strong><br>
                    Sie bieten: <strong><?php echo htmlspecialchars($req['offers_player']); ?></strong><br>
                    Budget-Differenz: <span class="<?php echo (strpos($req['money_transfer'], '+') !== false) ? 'text-success' : 'text-danger'; ?> font-bold">
                        <?php echo htmlspecialchars($req['money_transfer']); ?> Coins
                    </span>
                </div>
                <div class="trade-actions">
                    <button class="primary-btn success-btn" onclick="acceptTrade(<?php echo $req['id']; ?>)">Annehmen</button>
                    <button class="primary-btn danger-btn" onclick="declineTrade(<?php echo $req['id']; ?>)">Ablehnen</button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- HIER KOMMT SPÄTER DAS POPUP FÜR DAS TAUSCH-FORMULAR HIN -->
 <!-- =========================================
     DAS TAUSCH-MODAL (Popup)
     ========================================= -->
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
                    <option value="" disabled selected>-- Spieler wählen --</option>
                    <?php 
                    // Hier laden wir die eigenen Spieler ins Dropdown
                    foreach ($data['my_players'] as $my_player): ?>
                        <option value="<?php echo $my_player['price']; ?>">
                            <?php echo htmlspecialchars($my_player['name']); ?> (Wert: <?php echo $my_player['price']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="trade-calculation" class="trade-calc-box" style="display: none;">
                <strong>Kostenübersicht:</strong><br>
                Zahlung an anderes Team: <span id="trade-cost" class="font-bold">0</span> Coins<br>
                <small id="trade-warning" class="text-danger" style="display:none;">Nicht genug Budget!</small>
            </div>
        </div>
        
        <div class="modal-footer">
            <button class="primary-btn" id="submit-trade-btn" disabled onclick="sendTradeRequest()">Anfrage senden</button>
        </div>
    </div>
</div>

<!-- Eine unsichtbare Variable für das JS speichern -->
<input type="hidden" id="current-budget" value="<?php echo $data['my_team_budget']; ?>">