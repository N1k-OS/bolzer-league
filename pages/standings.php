<div class="page-header">
    <h2>Tabelle</h2>
    <p>Aktueller Stand im laufenden Event.</p>
</div>

<div class="card-container">
    <!-- Tabellen-Kopfzeile -->
    <div class="list-header">
        <div class="col-rank">#</div>
        <div class="col-team">Team</div>
        <div class="col-pts">Pkt.</div>
    </div>

    <!-- Tabellen-Inhalt -->
    <ul class="clean-list">
        <?php
        // 1. Daten laden (Später: $db->query("SELECT * FROM teams ORDER BY points DESC"))
        $standings_json = file_get_contents('data/standings.json');
        $standings_data = json_decode($standings_json, true);

        // 2. Schleife über alle Teams
        foreach ($standings_data as $team) {
            echo '<li class="list-item">';
            
            // Platzierung
            echo '  <div class="col-rank font-bold">' . htmlspecialchars($team['rank']) . '.</div>';
            
            // Team (Kreis + Name)
            echo '  <div class="col-team flex-align-center">';
            echo '    <div class="avatar-circle">' . htmlspecialchars($team['icon']) . '</div>';
            echo '    <span class="font-bold">' . htmlspecialchars($team['name']) . '</span>';
            echo '  </div>';
            
            // Punkte
            echo '  <div class="col-pts font-bold color-primary">' . htmlspecialchars($team['points']) . '</div>';
            
            echo '</li>';
        }
        ?>
    </ul>
</div>