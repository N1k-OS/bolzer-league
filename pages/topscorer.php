<div class="page-header">
    <h2>Top Scorer</h2>
    <p>Die besten Torschützen des aktuellen Events.</p>
</div>

<div class="card-container">
    <div class="list-header">
        <div class="col-rank">#</div>
        <div class="col-team">Spieler</div>
        <div class="col-pts">Tore</div>
    </div>

    <ul class="clean-list">
        <?php
        require_once __DIR__ . '/../includes/bootstrap.php';
        $database = new Database();
        $db = $database->getConnection();

        try {
            $event_stmt = $db->query("SELECT id FROM events WHERE status = 'active' LIMIT 1");
            $current_event = $event_stmt->fetch();

            if (!$current_event) {
                 echo "<li class='list-item' style='padding: 20px; color: gray;'>Kein aktives Event gefunden.</li>";
            } else {
                $event_id = $current_event['id'];

                // 2. Abfrage: Alle Torschützen für dieses Event auflisten und nach Toren sortieren
                $sql = "
                    SELECT 
                        u.alias, 
                        u.icon, 
                        t.icon AS team_icon,
                        SUM(mg.goals) AS total_goals
                    FROM match_goals mg
                    JOIN matches m ON mg.match_id = m.id
                    JOIN matchdays md ON m.matchday_id = md.id
                    JOIN users u ON mg.user_id = u.id
                    JOIN rosters r ON (r.user_id = u.id AND r.event_id = :event_id)
                    JOIN teams t ON r.team_id = t.id
                    WHERE md.event_id = :event_id
                    GROUP BY u.id, t.id
                    HAVING total_goals > 0
                    ORDER BY total_goals DESC, u.alias ASC
                    LIMIT 10
                ";

                $stmt = $db->prepare($sql);
                $stmt->execute([':event_id' => $event_id]);
                $scorers = $stmt->fetchAll();

                if (count($scorers) === 0) {
                    echo "<li class='list-item' style='padding: 20px; color: gray;'>Noch wurden keine Tore in diesem Event erzielt.</li>";
                }

                // 3. Ausgabe
                $rank = 1;
                foreach ($scorers as $player) {
                    $initial = empty($player['icon']) ? mb_substr($player['alias'], 0, 1) : $player['icon'];
                    
                    echo '<li class="list-item">';
                    echo '  <div class="col-rank font-bold">' . $rank . '.</div>';
                    
                    echo '  <div class="col-team flex-align-center">';
                    echo '    <div class="avatar-circle" style="background-color: var(--primary-color); color: white; border: none;">' . htmlspecialchars($initial) . '</div>';
                    echo '    <div class="flex-column">';
                    echo '      <span class="font-bold">' . htmlspecialchars($player['alias']) . '</span>';
                    echo '      <span style="font-size: 0.75rem; color: gray;">Team ' . htmlspecialchars($player['team_icon']) . '</span>';
                    echo '    </div>';
                    echo '  </div>';
                    
                    echo '  <div class="col-pts font-bold color-primary">' . htmlspecialchars($player['total_goals']) . '</div>';
                    echo '</li>';
                    
                    $rank++;
                }
            }
        } catch (Exception $e) {
             echo "<li class='list-item text-danger'>Fehler beim Laden der Top-Scorer.</li>";
        }
        ?>
    </ul>
</div>