<div class="page-header">
    <h2>Begegnungen</h2>
    <p>Spielplan und Ergebnisse.</p>
</div>

<?php
require_once 'includes/db.php';
$database = new Database();
$db = $database->getConnection();

try {
    // 1. Aktives Event holen
    $event_stmt = $db->query("SELECT id FROM events WHERE status = 'active' LIMIT 1");
    $current_event = $event_stmt->fetch();

    if (!$current_event) {
        echo "<p style='color: gray; text-align: center; padding: 20px;'>Kein aktives Event gefunden.</p>";
    } else {
        $event_id = $current_event['id'];

        // 2. Alle Matches für dieses Event holen inkl. Team-Infos und MVP-Namen
        $sql = "
            SELECT 
                m.id AS match_id,
                m.status,
                m.score1,
                m.score2,
                md.matchday_number,
                t1.name AS team1_name, t1.icon AS team1_icon,
                t2.name AS team2_name, t2.icon AS team2_icon,
                u.alias AS mvp_name
            FROM matches m
            JOIN matchdays md ON m.matchday_id = md.id
            JOIN teams t1 ON m.team1_id = t1.id
            JOIN teams t2 ON m.team2_id = t2.id
            LEFT JOIN users u ON m.mvp_user_id = u.id
            WHERE md.event_id = :event_id
            ORDER BY md.matchday_number ASC, m.id ASC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':event_id' => $event_id]);
        $all_matches = $stmt->fetchAll();

        // 3. In PHP sortieren und gruppieren
        $upcoming = [];
        $finished = [];

        foreach ($all_matches as $match) {
            $day = $match['matchday_number'];
            if ($match['status'] === 'upcoming') {
                if (!isset($upcoming[$day])) $upcoming[$day] = [];
                $upcoming[$day][] = $match;
            } else {
                if (!isset($finished[$day])) $finished[$day] = [];
                $finished[$day][] = $match;
            }
        }
        
        // Vergangene Spieltage absteigend sortieren (neueste oben)
        krsort($finished); 
        ?>

        <div class="accordion-container">
            <!-- AKKORDEON 1: ANSTEHENDE SPIELE -->
            <div class="accordion-item" style="border-left: 4px solid var(--primary-color);">
                <button class="accordion-header">
                    <span class="team-name">⏳ Anstehende Spiele</span>
                    <span class="accordion-icon">−</span>
                </button>
                <div class="accordion-content" style="max-height: 2000px;">
                    <div class="matches-wrapper" style="padding: 10px;">
                        
                        <?php if (empty($upcoming)): ?>
                            <p style="padding: 10px; color: gray; font-size: 0.9rem;">Keine anstehenden Spiele.</p>
                        <?php else: ?>
                            <?php foreach ($upcoming as $day_num => $matches): ?>
                                <div class="matchday-group">
                                    <div class="matchday-label">Spieltag <?php echo $day_num; ?></div>
                                    
                                    <?php foreach ($matches as $match): ?>
                                        <div class="match-card">
                                            <div class="match-teams">
                                                <div class="m-team right-align">
                                                    <span class="m-name"><?php echo htmlspecialchars($match['team1_name']); ?></span>
                                                    <span class="m-icon"><?php echo htmlspecialchars($match['team1_icon']); ?></span>
                                                </div>
                                                <div class="m-vs">vs</div>
                                                <div class="m-team left-align">
                                                    <span class="m-icon"><?php echo htmlspecialchars($match['team2_icon']); ?></span>
                                                    <span class="m-name"><?php echo htmlspecialchars($match['team2_name']); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

            <!-- AKKORDEON 2: VERGANGENE SPIELE -->
            <div class="accordion-item" style="border-left: 4px solid gray;">
                <button class="accordion-header">
                    <span class="team-name">✅ Vergangene Spiele</span>
                    <span class="accordion-icon">+</span>
                </button>
                <div class="accordion-content">
                    <div class="matches-wrapper" style="padding: 10px;">
                        
                        <?php if (empty($finished)): ?>
                            <p style="padding: 10px; color: gray; font-size: 0.9rem;">Noch keine Spiele beendet.</p>
                        <?php else: ?>
                            <?php foreach ($finished as $day_num => $matches): ?>
                                <div class="matchday-group">
                                    <div class="matchday-label">Spieltag <?php echo $day_num; ?></div>
                                    
                                    <?php foreach ($matches as $match): ?>
                                        <div class="match-card finished">
                                            <div class="match-teams">
                                                <div class="m-team right-align font-bold">
                                                    <span class="m-name"><?php echo htmlspecialchars($match['team1_name']); ?></span>
                                                    <span class="m-icon"><?php echo htmlspecialchars($match['team1_icon']); ?></span>
                                                </div>
                                                <div class="m-score">
                                                    <?php echo $match['score1']; ?> : <?php echo $match['score2']; ?>
                                                </div>
                                                <div class="m-team left-align font-bold">
                                                    <span class="m-icon"><?php echo htmlspecialchars($match['team2_icon']); ?></span>
                                                    <span class="m-name"><?php echo htmlspecialchars($match['team2_name']); ?></span>
                                                </div>
                                            </div>
                                            <?php if (!empty($match['mvp_name'])): ?>
                                            <div class="match-mvp">
                                                <small>⭐ MVP: <?php echo htmlspecialchars($match['mvp_name']); ?></small>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>

        <?php
    }
} catch (Exception $e) {
    echo "<p class='text-danger' style='padding: 20px;'>Fehler beim Laden des Spielplans.</p>";
}
?>