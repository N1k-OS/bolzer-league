<div class="page-header">
    <h2>Tabelle</h2>
    <p>Aktueller Stand im laufenden Event.</p>
</div>

<div class="card-container">
    <div class="list-header">
        <div class="col-rank">#</div>
        <div class="col-team">Team</div>
        <div class="col-pts">Pkt.</div>
    </div>

    <ul class="clean-list">
        <?php
        require_once 'includes/db.php';
        $database = new Database();
        $db = $database->getConnection();

        try {
            // 1. Das aktive Event finden
            $event_stmt = $db->query("SELECT id FROM events WHERE status = 'active' LIMIT 1");
            $current_event = $event_stmt->fetch();

            if (!$current_event) {
                 echo '<li class="list-item" style="padding: 20px; color: gray;">Kein aktives Event gefunden.</li>';
            } else {
                $event_id = $current_event['id'];

                // 2. Komplexe SQL-Abfrage: Alle Teams laden und aus den fertigen Matches die Punkte berechnen!
                // Wir zählen: 3 Punkte für einen Sieg, 1 für ein Unentschieden.
                $sql = "
                    SELECT 
                        t.id, 
                        t.name, 
                        t.icon,
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
                    ORDER BY points DESC, t.name ASC
                ";

                $stmt = $db->prepare($sql);
                $stmt->execute([':event_id' => $event_id]);
                $teams = $stmt->fetchAll();

                // 3. Ausgabe der Liste
                $rank = 1;
                foreach ($teams as $team) {
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
            }
        } catch (Exception $e) {
            echo "<li class='list-item text-danger'>Fehler beim Laden der Tabelle.</li>";
        }
        ?>
    </ul>
</div>