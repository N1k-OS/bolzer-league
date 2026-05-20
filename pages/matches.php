<div class="page-header">
    <h2>Begegnungen</h2>
    <p>Spielplan und Ergebnisse.</p>
</div>

<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/tournament.php';
$database = new Database();
$db = $database->getConnection();

function render_match_teams_row(array $match, bool $finished = false): void
{
    $t1_label = display_team_label($match['team1_name']);
    $t2_label = display_team_label($match['team2_name']);
    $t1_tba = is_tba_slot($match['team1_id'] ?? null, $match['team1_name']);
    $t2_tba = is_tba_slot($match['team2_id'] ?? null, $match['team2_name']);
    $t1_icon = $t1_tba ? '?' : ($match['team1_icon'] ?? '');
    $t2_icon = $t2_tba ? '?' : ($match['team2_icon'] ?? '');
    $bold = $finished ? ' font-bold' : '';
    ?>
    <div class="match-teams">
        <div class="m-team right-align<?php echo $bold; ?><?php echo $t1_tba ? ' tba-slot' : ''; ?>">
            <span class="m-name"><?php echo htmlspecialchars($t1_label); ?></span>
            <span class="m-icon"><?php echo htmlspecialchars($t1_icon); ?></span>
        </div>
        <?php if ($finished): ?>
            <div class="m-score"><?php echo (int) $match['score1']; ?> : <?php echo (int) $match['score2']; ?></div>
        <?php else: ?>
            <div class="m-vs">vs</div>
        <?php endif; ?>
        <div class="m-team left-align<?php echo $bold; ?><?php echo $t2_tba ? ' tba-slot' : ''; ?>">
            <span class="m-icon"><?php echo htmlspecialchars($t2_icon); ?></span>
            <span class="m-name"><?php echo htmlspecialchars($t2_label); ?></span>
        </div>
    </div>
    <?php
}

try {
    $event_stmt = $db->query("SELECT id FROM events WHERE status = 'active' LIMIT 1");
    $current_event = $event_stmt->fetch();

    if (!$current_event) {
        echo '<p class="page-empty">Kein aktives Event gefunden.</p>';
    } else {
        $event_id = $current_event['id'];

        $sql = "
            SELECT 
                m.id AS match_id,
                m.team1_id,
                m.team2_id,
                m.status,
                m.score1,
                m.score2,
                md.matchday_number,
                t1.name AS team1_name, t1.icon AS team1_icon,
                t2.name AS team2_name, t2.icon AS team2_icon,
                u.alias AS mvp_name
            FROM matches m
            JOIN matchdays md ON m.matchday_id = md.id
            LEFT JOIN teams t1 ON m.team1_id = t1.id
            LEFT JOIN teams t2 ON m.team2_id = t2.id
            LEFT JOIN users u ON m.mvp_user_id = u.id
            WHERE md.event_id = :event_id
            ORDER BY md.matchday_number ASC, m.id ASC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':event_id' => $event_id]);
        $all_matches = $stmt->fetchAll();

        $upcoming = [];
        $finished = [];

        foreach ($all_matches as $match) {
            $day = $match['matchday_number'];
            if ($match['status'] === 'upcoming') {
                if (!isset($upcoming[$day])) {
                    $upcoming[$day] = [];
                }
                $upcoming[$day][] = $match;
            } else {
                if (!isset($finished[$day])) {
                    $finished[$day] = [];
                }
                $finished[$day][] = $match;
            }
        }
        
        krsort($finished);
        ?>

        <div class="accordion-container">
            <div class="accordion-item accordion-item--accent">
                <button class="accordion-header">
                    <span class="team-name">⏳ Anstehende Spiele</span>
                    <span class="accordion-icon">−</span>
                </button>
                <div class="accordion-content accordion-content--open">
                    <div class="matches-wrapper u-p-10">
                        <?php if (empty($upcoming)): ?>
                            <p class="u-p-10 u-text-muted-sm">Keine anstehenden Spiele.</p>
                        <?php else: ?>
                            <?php foreach ($upcoming as $day_num => $matches): ?>
                                <div class="matchday-group">
                                    <div class="matchday-label">Spieltag <?php echo $day_num; ?></div>
                                    <?php foreach ($matches as $match): ?>
                                        <div class="match-card">
                                            <?php render_match_teams_row($match, false); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="accordion-item accordion-item--muted">
                <button class="accordion-header">
                    <span class="team-name">✅ Vergangene Spiele</span>
                    <span class="accordion-icon">+</span>
                </button>
                <div class="accordion-content">
                    <div class="matches-wrapper u-p-10">
                        <?php if (empty($finished)): ?>
                            <p class="u-p-10 u-text-muted-sm">Noch keine Spiele beendet.</p>
                        <?php else: ?>
                            <?php foreach ($finished as $day_num => $matches): ?>
                                <div class="matchday-group">
                                    <div class="matchday-label">Spieltag <?php echo $day_num; ?></div>
                                    <?php foreach ($matches as $match): ?>
                                        <div class="match-card finished">
                                            <?php render_match_teams_row($match, true); ?>
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
    echo '<p class="text-danger page-error">Fehler beim Laden des Spielplans.</p>';
}
?>
