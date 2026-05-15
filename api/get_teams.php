<?php
// api/get_teams.php
header("Content-Type: application/json; charset=UTF-8");
require_once '../includes/db.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // 1. Aktives Event suchen
    $event_stmt = $db->query("SELECT id FROM events WHERE status = 'active' LIMIT 1");
    $current_event = $event_stmt->fetch();

    if (!$current_event) {
        echo json_encode([]); // Leeres Array, wenn kein Event aktiv ist
        exit;
    }

    $event_id = $current_event['id'];

    // 2. Teams laden
    $teams_stmt = $db->prepare("SELECT id, name, icon FROM teams WHERE event_id = ?");
    $teams_stmt->execute([$event_id]);
    $teams_data = $teams_stmt->fetchAll();

    $result = [];

    // 3. Spieler für jedes Team laden
    foreach ($teams_data as $team) {
        $roster_stmt = $db->prepare("
            SELECT u.alias AS name, r.current_category AS category 
            FROM rosters r 
            JOIN users u ON r.user_id = u.id 
            WHERE r.event_id = ? AND r.team_id = ?
        ");
        $roster_stmt->execute([$event_id, $team['id']]);
        $players = $roster_stmt->fetchAll();

        // Das cat_label generieren ("Kat A" etc.)
        foreach ($players as &$player) {
            $player['cat_label'] = 'Kat ' . strtoupper($player['category']);
        }

        $result[] = [
            'id' => $team['id'],
            'name' => $team['name'],
            'icon' => $team['icon'],
            'players' => $players
        ];
    }

    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode(['error' => 'Datenbankfehler beim Laden der Teams']);
}
?>