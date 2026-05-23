<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/tournament.php';

$database = new Database();
$db = $database->getConnection();

try {
    $event_stmt = $db->query("SELECT id FROM events WHERE status = 'active' LIMIT 1");
    $current_event = $event_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current_event) {
        echo '<div class="card-container"><ul class="clean-list"><li class="list-item" style="padding: 20px; color: gray;">Kein aktives Event gefunden.</li></ul></div>';
    } else {
        $event_id = $current_event['id'];
        $grouped_teams = get_group_standings($db, $event_id);

        if (empty($grouped_teams)) {
            echo '<p class="page-empty">Noch keine Teams vorhanden.</p>';
        } else {
            foreach ($grouped_teams as $g_name => $g_teams) {
                if ($g_name !== 'Tabelle') {
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
                    $icon = $team['icon'] ?? substr($team['name'], 0, 1);
                    
                    echo '<li class="list-item">';
                    echo '  <div class="col-rank font-bold">' . $rank . '.</div>';
                    echo '  <div class="col-team flex-align-center">';
                    echo '    <div class="avatar-circle">' . htmlspecialchars($icon) . '</div>';
                    echo '    <span class="font-bold">' . htmlspecialchars($team['name']) . '</span>';
                    echo '  </div>';
                    echo '  <div class="col-pts font-bold color-primary">' . htmlspecialchars($team['pts']) . '</div>';
                    echo '</li>';
                    $rank++;
                }
                
                echo '</ul></div>';
            }
        }
    }
} catch (Exception $e) {
    echo "<div class='card-container'><ul class='clean-list'><li class='list-item text-danger'>Fehler beim Laden der Tabelle: " . $e->getMessage() . "</li></ul></div>";
}
?>