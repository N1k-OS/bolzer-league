<div class="page-header">
    <h2>Tabelle</h2>
    <p>Aktueller Stand im laufenden Event.</p>
</div>

<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$database = new Database();
$db = $database->getConnection();

try {
    $event_stmt = $db->query("SELECT id FROM events WHERE status = 'active' LIMIT 1");
    $current_event = $event_stmt->fetch();

    if (!$current_event) {
        echo '<div class="card-container"><ul class="clean-list"><li class="list-item" style="padding: 20px; color: gray;">Kein aktives Event gefunden.</li></ul></div>';
    } else {
        $event_id = $current_event['id'];

        $has_groups = false;
        try {
            $stmt = $db->query("SHOW COLUMNS FROM teams LIKE 'group_name'");
            if ($stmt && $stmt->rowCount() > 0) {
                $has_groups = true;
            }
        } catch (Exception $e) {}

        $group_col = $has_groups ? "t.group_name," : "NULL as group_name,";

        $sql = "
            SELECT 
                t.id, 
                t.name, 
                t.icon,
                $group_col
                COALESCE(
                    (SELECT SUM(
                        CASE 
                            WHEN m.team1_id = t.id AND m.score1 > m.score2 THEN 3
                            WHEN m.team2_id = t.id AND m.score2 > m.score1 THEN 3
                            WHEN m.score1 = m.score2 THEN 1
                            ELSE 0
                        END
                    ) 
                    FROM matches m 
                    JOIN matchdays md ON m.matchday_id = md.id
                    WHERE md.event_id = :event_id AND m.status = 'finished' 
                    AND (m.team1_id = t.id OR m.team2_id = t.id)), 
                0) AS points
            FROM teams t
            WHERE t.event_id = :event_id
            ORDER BY " . ($has_groups ? "t.group_name ASC," : "") . " points DESC, t.name ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([':event_id' => $event_id]);
        $teams = $stmt->fetchAll();

        $grouped_teams = [];
        foreach ($teams as $t) {
            $g = $t['group_name'] ?: 'Alle';
            if (!isset($grouped_teams[$g])) {
                $grouped_teams[$g] = [];
            }
            $grouped_teams[$g][] = $t;
        }

        foreach ($grouped_teams as $g_name => $g_teams) {
            if ($g_name !== 'Alle') {
                echo "<h3 style='margin-top: 30px; margin-bottom: 10px; color: var(--text-color);'>" . htmlspecialchars($g_name) . "</h3>";
            }
            echo '<div class="card-container">';
            echo '<div class="list-header">';
            echo '    <div class="col-rank">#</div>';
            echo '    <div class="col-team">Team</div>';
            echo '    <div class="col-pts">Pkt.</div>';
            echo '</div>';
            echo '<ul class="clean-list">';
            
            $rank = 1;
            foreach ($g_teams as $team) {
                echo '<li class="list-item">';
                echo '  <div class="col-rank font-bold">' . $rank . '.</div>';
                echo '  <div class="col-team flex-align-center">';
                echo '    <div class="avatar-circle">' . htmlspecialchars($team['icon']) . '</div>';
                echo '    <span class="font-bold">' . htmlspecialchars($team['name']) . '</span>';
                echo '  </div>';
                echo '  <div class="col-pts font-bold color-primary">' . htmlspecialchars($team['points']) . '</div>';
                echo '</li>';
                $rank++;
            }
            
            echo '</ul></div>';
        }
    }
} catch (Exception $e) {
    echo "<div class='card-container'><ul class='clean-list'><li class='list-item text-danger'>Fehler beim Laden der Tabelle.</li></ul></div>";
}
?>