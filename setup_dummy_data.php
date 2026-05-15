<?php
// setup_dummy_data.php
require_once 'includes/db.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // 1. Ein Event erstellen
    $db->exec("INSERT INTO events (name, event_date, duration_type, status) VALUES ('Eröffnungsturnier', '2026-05-20', 'standard', 'active')");
    $event_id = $db->lastInsertId();

    // 2. Vier Teams erstellen
    $teams = [
        ['Team Alpha', '🛡️'],
        ['Team Bravo', '🦁'],
        ['Team Charlie', '🦅'],
        ['Team Delta', '🐍']
    ];
    $team_ids = [];
    foreach ($teams as $team) {
        $stmt = $db->prepare("INSERT INTO teams (event_id, name, icon, budget) VALUES (?, ?, ?, 150)");
        $stmt->execute([$event_id, $team[0], $team[1]]);
        $team_ids[] = $db->lastInsertId();
    }

    // 3. Deinen frisch registrierten User (ID = 1) in Team 1 (Alpha) stecken
    // Ich gehe davon aus, dass dein erstellter Account die ID 1 hat.
    $stmt = $db->prepare("INSERT INTO rosters (event_id, team_id, user_id, current_category, current_price) VALUES (?, ?, ?, 'c', 50)");
    $stmt->execute([$event_id, $team_ids[0], 1]);

    echo "Erfolgreich! Event, 4 Teams und du im Kader wurden angelegt. Du kannst diese Datei jetzt wieder vom Server löschen.";
} catch (Exception $e) {
    echo "Fehler: " . $e->getMessage();
}
?>