<div class="page-header">
    <h2>Turnierbaum</h2>
    <p>Der Weg zum Titel.</p>
</div>

<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/tournament.php';

function render_bracket_match_card(array $match, bool $is_third_place = false): void
{
    $t1_label = display_team_label($match['team1_name']);
    $t2_label = display_team_label($match['team2_name']);
    $t1_tba = is_tba_slot($match['team1_id'] ?? null, $match['team1_name']);
    $t2_tba = is_tba_slot($match['team2_id'] ?? null, $match['team2_name']);
    $t1_winner = ($match['status'] === 'finished' && !$t1_tba && !$t2_tba && $match['score1'] >= $match['score2']);
    $t2_winner = ($match['status'] === 'finished' && !$t1_tba && !$t2_tba && $match['score2'] > $match['score1']);
    $status_class = htmlspecialchars($match['status']);
    $extra_class = $is_third_place ? ' third-place-match' : '';
    ?>
    <div class="bracket-match <?php echo $status_class . $extra_class; ?>">
        <div class="b-team <?php echo $t1_winner ? 'winner' : ''; ?> <?php echo $t1_tba ? 'tba-slot' : ''; ?>">
            <span class="b-name"><?php echo htmlspecialchars($t1_label); ?></span>
            <span class="b-score"><?php echo ($match['score1'] !== null) ? (int) $match['score1'] : '-'; ?></span>
        </div>
        <div class="b-team <?php echo $t2_winner ? 'winner' : ''; ?> <?php echo $t2_tba ? 'tba-slot' : ''; ?>">
            <span class="b-name"><?php echo htmlspecialchars($t2_label); ?></span>
            <span class="b-score"><?php echo ($match['score2'] !== null) ? (int) $match['score2'] : '-'; ?></span>
        </div>
    </div>
    <?php
}

$database = new Database();
$db = $database->getConnection();

try {
    $event_stmt = $db->query("SELECT id, duration_type FROM events WHERE status = 'active' LIMIT 1");
    $current_event = $event_stmt->fetch();

    if (!$current_event) {
        echo '<p class="page-empty">Kein aktives Event gefunden.</p>';
    } elseif ($current_event['duration_type'] !== 'kurz') {
        echo '<div class="alert-box">'
            . '<strong>Ligamodus aktiv!</strong><br><br>'
            . 'Der Turnierbaum ist nur im Elimination-Modus verfügbar. Nutze die Tabelle.'
            . '</div>';
    } else {
        $event_id = $current_event['id'];

        $sql = "
            SELECT
                m.id, m.team1_id, m.team2_id, m.score1, m.score2, m.status, md.matchday_number,
                t1.name AS team1_name, t2.name AS team2_name
            FROM matches m
            JOIN matchdays md ON m.matchday_id = md.id
            LEFT JOIN teams t1 ON m.team1_id = t1.id
            LEFT JOIN teams t2 ON m.team2_id = t2.id
            WHERE md.event_id = :event_id
            ORDER BY md.matchday_number ASC, m.id ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([':event_id' => $event_id]);
        $all_matches = $stmt->fetchAll();

        if (empty($all_matches)) {
            echo '<p class="page-empty">Noch kein Spielplan angelegt.</p>';
        } else {
            $rounds = [];
            foreach ($all_matches as $match) {
                $day = $match['matchday_number'];
                if (!isset($rounds[$day])) {
                    $rounds[$day] = ['round_name' => 'Runde ' . $day, 'matches' => []];
                }
                $rounds[$day]['matches'][] = $match;
            }

            $last_matchday = max(array_keys($rounds));

            foreach ($rounds as $day_num => &$round) {
                $match_count = count($round['matches']);
                if ($day_num === $last_matchday && $match_count === 2) {
                    continue;
                }
                $round['round_name'] = ko_round_display_name($match_count);
            }
            unset($round);
            ?>

            <div class="bracket-scroll-container">
                <div class="bracket-wrapper">
                    <?php foreach ($rounds as $day_num => $round):
                        $is_finals_column = ($day_num === $last_matchday && count($round['matches']) === 2);
                        ?>
                        <div class="bracket-round">
                            <?php if ($is_finals_column):
                                $finale_match = $round['matches'][0];
                                $third_match = $round['matches'][1];
                                ?>
                                <div class="bracket-finals-column">
                                    <div class="bracket-subround">
                                        <div class="round-title round-title--sub">Finale</div>
                                        <?php render_bracket_match_card($finale_match, false); ?>
                                    </div>
                                    <div class="bracket-subround bracket-subround--third">
                                        <div class="round-title round-title--sub">Spiel um Platz 3 und 4</div>
                                        <?php render_bracket_match_card($third_match, true); ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="round-title"><?php echo htmlspecialchars($round['round_name']); ?></div>
                                <div class="round-matches">
                                    <?php foreach ($round['matches'] as $index => $match):
                                        $is_last_in_round = ($index === count($round['matches']) - 1);
                                        render_bracket_match_card($match, false);
                                        if ($day_num < $total_rounds && !$is_last_in_round): ?>
                                            <div class="bracket-connector"></div>
                                        <?php endif;
                                    endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
        }
    }
} catch (Exception $e) {
    echo '<p class="text-danger page-error">Fehler.</p>';
}
?>
