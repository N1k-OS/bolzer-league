<div class="page-header">
    <h2>Teams & Kader</h2>
    <p>Die Aufstellungen für das aktuelle Event.</p>
</div>

<div class="accordion-container" id="teams-accordion">
    <?php
    // 1. Daten laden (Später: $db->query("SELECT * FROM teams..."))
    $teams_json = file_get_contents('data/teams.json');
    $teams_data = json_decode($teams_json, true);

    // 2. Schleife über alle Teams
    foreach ($teams_data as $team) {
        echo '<div class="accordion-item">';
        
        // Header des Teams
        echo '<button class="accordion-header">';
        echo '  <span class="team-name">' . htmlspecialchars($team['icon'] . ' ' . $team['name']) . '</span>';
        echo '  <span class="accordion-icon">+</span>';
        echo '</button>';
        
        // Inhalt (Spieler) des Teams
        echo '<div class="accordion-content">';
        echo '  <ul class="player-list">';
        
        // 3. Schleife über alle Spieler dieses Teams
        foreach ($team['players'] as $player) {
            // Generiere den Anfangsbuchstaben für den Kreis
            $initial = mb_substr($player['name'], 0, 1);
            $cat_class = 'category-' . $player['category']; // z.B. category-a
            
            echo '    <li class="player-item">';
            echo '      <div class="player-avatar">' . htmlspecialchars($initial) . '</div>';
            echo '      <div class="player-info">';
            echo '        <span class="player-name">' . htmlspecialchars($player['name']) . '</span>';
            echo '        <span class="player-cat ' . htmlspecialchars($cat_class) . '">' . htmlspecialchars($player['cat_label']) . '</span>';
            echo '      </div>';
            echo '    </li>';
        }
        
        echo '  </ul>';
        echo '</div>'; // Ende accordion-content
        
        echo '</div>'; // Ende accordion-item
    }
    ?>
</div>

<!-- Admin-Button (Wird später nur eingeblendet, wenn $user_role == 'admin') -->
<div class="admin-actions" style="margin-top: 20px; text-align: center;">
    <button class="primary-btn" style="width: auto; padding: 10px 20px;">+ Team hinzufügen</button>
</div>