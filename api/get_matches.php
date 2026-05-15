<?php
// api/get_matches.php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) exit;

$database = new Database();
$db = $database->getConnection();

// Holt alle Matches, die 'upcoming' sind und bei denen beide Teams feststehen (nicht NULL)
$sql = "
    SELECT m.id, t1.name AS team1, t2.name AS team2, md.matchday_number 
    FROM matches m
    JOIN matchdays md ON m.matchday_id = md.id
    JOIN events e ON md.event_id = e.id
    JOIN teams t1 ON m.team1_id = t1.id
    JOIN teams t2 ON m.team2_id = t2.id
    WHERE e.status = 'active' AND m.status = 'upcoming'
    ORDER BY md.matchday_number ASC, m.id ASC
";
$matches = $db->query($sql)->fetchAll();
echo json_encode($matches);
?>