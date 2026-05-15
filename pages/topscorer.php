<div class="page-header">
    <h2>Top Scorer</h2>
    <p>Die besten Torschützen des aktuellen Events.</p>
</div>

<div class="card-container">
    <!-- Tabellen-Kopfzeile -->
    <div class="list-header">
        <div class="col-rank">#</div>
        <div class="col-team">Spieler</div>
        <div class="col-pts">Tore</div>
    </div>

    <!-- Tabellen-Inhalt -->
    <ul class="clean-list">
        <?php
        // 1. Daten laden (Später SQL: SELECT name, goals FROM players ORDER BY goals DESC)
        $scorer_json = file_get_contents('data/topscorer.json');
        $scorers = json_decode($scorer_json, true);

        // 2. Schleife über alle Torschützen
        foreach ($scorers as $player) {
            $initial = mb_substr($player['name'], 0, 1);
            
            echo '<li class="list-item">';
            
            // Platzierung
            echo '  <div class="col-rank font-bold">' . htmlspecialchars($player['rank']) . '.</div>';
            
            // Spieler (Kreis mit Initial + Name + Team-Logo klein daneben)
            echo '  <div class="col-team flex-align-center">';
            echo '    <div class="avatar-circle" style="background-color: var(--primary-color); color: white; border: none;">' . htmlspecialchars($initial) . '</div>';
            echo '    <div class="flex-column">';
            echo '      <span class="font-bold">' . htmlspecialchars($player['name']) . '</span>';
            echo '      <span style="font-size: 0.75rem; color: gray;">Team ' . htmlspecialchars($player['team_icon']) . '</span>';
            echo '    </div>';
            echo '  </div>';
            
            // Tore
            echo '  <div class="col-pts font-bold color-primary">' . htmlspecialchars($player['goals']) . '</div>';
            
            echo '</li>';
        }
        ?>
    </ul>
</div>