<div class="page-header">
    <h2>Turnierbaum</h2>
    <p>Der Weg zum Titel.</p>
</div>

<?php
require_once 'includes/db.php';
$database = new Database();
$db = $database->getConnection();

try {
    $event_stmt = $db->query("SELECT id, duration_type FROM events WHERE status = 'active' LIMIT 1");
    $current_event = $event_stmt->fetch();

    if (!$current_event) {
        echo "<p style='color: gray; text-align: center; padding: 20px;'>Kein aktives Event gefunden.</p>";
    } elseif ($current_event['duration_type'] !== 'kurz') {
        echo "<div class='alert-box' style='margin: 20px; padding: 20px; text-align: center; color: gray; border: 1px solid var(--border-color); border-radius: 8px;'>";
        echo "<strong>Ligamodus aktiv!</strong><br><br>Der Turnierbaum ist nur im Elimination-Modus verfügbar. Nutze die Tabelle.";
        echo "</div>";
    } else {
        $event_id = $current_event['id'];

        $sql = "
            SELECT 
                m.id, m.score1, m.score2, m.status, md.matchday_number,
                t1.name AS team1_name, t2.name AS team2_name
            FROM matches m
            JOIN matchdays md ON m.matchday_id = md.id
            JOIN teams t1 ON m.team1_id = t1.id
            JOIN teams t2 ON m.team2_id = t2.id
            WHERE md.event_id = :event_id
            ORDER BY md.matchday_number ASC, m.id ASC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':event_id' => $event_id]);
        $all_matches = $stmt->fetchAll();

        if (empty($all_matches)) {
            echo "<p style='color: gray; text-align: center; padding: 20px;'>Noch kein Spielplan generiert.</p>";
        } else {
            $rounds = [];
            foreach ($all_matches as $match) {
                $day = $match['matchday_number'];
                if (!isset($rounds[$day])) {
                    $rounds[$day] = ['round_name' => 'Runde ' . $day, 'matches' => []];
                }
                $rounds[$day]['matches'][] = $match;
            }

            // Benennung der Runden aufhübschen
            $total_rounds = count($rounds);
            if ($total_rounds == 2) {
                $rounds[1]['round_name'] = 'Halbfinale';
                $rounds[2]['round_name'] = 'Finale / Platz 3';
            }
            ?>

            <div class="bracket-scroll-container">
                <div class="bracket-wrapper">
                    <?php foreach ($rounds as $day_num => $round): ?>
                        <div class="bracket-round">
                            <div class="round-title"><?php echo htmlspecialchars($round['round_name']); ?></div>
                            <div class="round-matches">
                                <?php foreach ($round['matches'] as $index => $match): 
                                    // Letzte Runde: Wenn es nur 2 Spiele sind, ist das Zweite meist Platz 3
                                    $is_third_place = ($day_num == $total_rounds && count($round['matches']) == 2 && $index == 1);
                                    
                                    $t1_winner = ($match['status'] === 'finished' && $match['score1'] >= $match['score2']);
                                    $t2_winner = ($match['status'] === 'finished' && $match['score2'] > $match['score1']);
                                ?>
                                    
                                    <?php if ($is_third_place): ?>
                                        <div style="text-align: center; font-size: 0.7rem; color: gray; margin: 10px 0 5px 0;">Spiel um Platz 3</div>
                                    <?php endif; ?>

                                    <div class="bracket-match <?php echo $match['status']; ?> <?php echo $is_third_place ? 'third-place-match' : ''; ?>">
                                        <div class="b-team <?php echo $t1_winner ? 'winner' : ''; ?>">
                                            <span class="b-name"><?php echo htmlspecialchars($match['team1_name']); ?></span>
                                            <span class="b-score"><?php echo ($match['score1'] !== null) ? $match['score1'] : '-'; ?></span>
                                        </div>
                                        <div class="b-team <?php echo $t2_winner ? 'winner' : ''; ?>">
                                            <span class="b-name"><?php echo htmlspecialchars($match['team2_name']); ?></span>
                                            <span class="b-score"><?php echo ($match['score2'] !== null) ? $match['score2'] : '-'; ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php if ($day_num < $total_rounds && !$is_third_place): ?>
                                        <div class="bracket-connector"></div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
        }
    }
} catch (Exception $e) {
    echo "<p class='text-danger' style='padding: 20px;'>Fehler.</p>";
}
?>