<?php
// api/get_admin_roster_overview.php — Kaderliste für Admin-Transfer (aktives Event)
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    echo json_encode(['success' => false, 'message' => 'Zugriff verweigert', 'teams' => [], 'players' => []]);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$event_stmt = $db->query("SELECT id FROM events WHERE status = 'active' LIMIT 1");
$event = $event_stmt->fetch();

if (!$event) {
    echo json_encode(['success' => false, 'message' => 'Kein aktives Event.', 'teams' => [], 'players' => []]);
    exit;
}

$event_id = (int) $event['id'];

$teams_stmt = $db->prepare('SELECT id, name FROM teams WHERE event_id = ? ORDER BY name');
$teams_stmt->execute([$event_id]);
$teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);

$players_stmt = $db->prepare("
    SELECT r.user_id, u.alias AS name, r.team_id, t.name AS team_name
    FROM rosters r
    JOIN users u ON r.user_id = u.id
    JOIN teams t ON r.team_id = t.id
    WHERE r.event_id = ?
    ORDER BY t.name, u.alias
");
$players_stmt->execute([$event_id]);
$players = $players_stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'teams' => $teams,
    'players' => $players,
]);
