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
    } elseif ($current_event['duration_type'] !== 'kurz' && $current_event['duration_type'] !== 'standard') {
        echo '<div class="alert-box">'
            . '<strong>Ligamodus aktiv!</strong><br><br>'
            . 'Der Turnierbaum ist nur im Elimination- oder WM-Modus verfügbar. Nutze die Tabelle.'
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

            if ($current_event['duration_type'] === 'standard') {
                $filtered_rounds = [];
                $days = array_keys($rounds);
                rsort($days);
                $expected_matches = 1;
                foreach ($days as $day) {
                    if (count($rounds[$day]['matches']) === $expected_matches) {
                        $filtered_rounds[$day] = $rounds[$day];
                        $expected_matches *= 2;
                    } else {
                        break;
                    }
                }
                ksort($filtered_rounds);
                $rounds = $filtered_rounds;
            }

            $last_matchday = !empty($rounds) ? max(array_keys($rounds)) : 0;

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
                    <?php if ($current_event['duration_type'] === 'standard'): 
                        $groups = get_group_standings($db, $event_id);
                        if (!empty($groups)):
                    ?>
                        <div class="bracket-round">
                            <div class="round-title">Gruppenphase</div>
                            <div class="round-matches">
                                <?php foreach ($groups as $g_name => $g_teams): ?>
                                    <div class="bracket-match" style="gap:0;">
                                        <div style="font-weight:bold; font-size: 0.85rem; padding: 5px; color: var(--text-color); border-bottom: 1px solid var(--border-color); text-align: center; background: rgba(0,0,0,0.2);">
                                            <?= htmlspecialchars($g_name) ?>
                                        </div>
                                        <?php foreach ($g_teams as $team): ?>
                                            <div class="b-team" style="border-bottom: 1px solid var(--border-color);">
                                                <span class="b-name"><?= htmlspecialchars($team['name']) ?></span>
                                                <span class="b-score" style="font-weight:normal; font-size:0.8rem; background:transparent; padding-right:5px;"><?= htmlspecialchars($team['pts']) ?> Pkt</span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; endif; ?>

                    <?php foreach ($rounds as $day_num => $round):
                        $is_finals = ($day_num === $last_matchday);
                        ?>
                        <div class="bracket-round">
                            <div class="round-title"><?php echo htmlspecialchars($round['round_name']); ?></div>
                            <div class="round-matches">
                                <?php foreach ($round['matches'] as $index => $match):
                                    render_bracket_match_card($match, false);
                                endforeach; ?>
                            </div>
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
