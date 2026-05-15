<?php
// api/admin.php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// Sicherheits-Check: Nur Admins!
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin'] || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Zugriff verweigert']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$action = $_POST['action'] ?? '';

// ---------------------------------------------------------
// SPIELPLAN GENERIEREN
// ---------------------------------------------------------
if ($action === 'generate_matchplan') {
    try {
        // 1. Aktives Event suchen
        $event_stmt = $db->query("SELECT id, duration_type FROM events WHERE status = 'active' LIMIT 1");
        $event = $event_stmt->fetch();

        if (!$event) {
            echo json_encode(['success' => false, 'message' => 'Es gibt kein aktives Event.']);
            exit;
        }

        $event_id = $event['id'];

        // 2. Teams laden
        $teams_stmt = $db->prepare("SELECT id FROM teams WHERE event_id = ?");
        $teams_stmt->execute([$event_id]);
        $teams = $teams_stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($teams) < 2) {
            echo json_encode(['success' => false, 'message' => 'Zu wenige Teams. Du brauchst mindestens 2.']);
            exit;
        }

        // 3. Vorherigen Spielplan löschen (falls man ihn neu generiert)
        // WICHTIG: Das Löscht auch alle Ergebnisse des aktuellen Events!
        $db->prepare("DELETE FROM matchdays WHERE event_id = ?")->execute([$event_id]);

        // 4. Round-Robin Algorithmus (Jeder gegen Jeden)
        // Wir mischen die Teams nicht, damit es deterministisch bleibt
        $num_teams = count($teams);
        $ghost = false;
        
        // Wenn ungerade Anzahl an Teams, brauchen wir ein "Geister-Team" (Freilos)
        if ($num_teams % 2 != 0) {
            $teams[] = 'GHOST';
            $num_teams++;
            $ghost = true;
        }

        $total_rounds = $num_teams - 1;
        $matches_per_round = $num_teams / 2;

        // Wie viele "Durchläufe"? (kurz = 1 Runde, standard = 2 Runden/Hin-Rück)
        $cycles = ($event['duration_type'] === 'standard') ? 2 : 1;
        $current_matchday = 1;

        for ($cycle = 0; $cycle < $cycles; $cycle++) {
            for ($round = 0; $round < $total_rounds; $round++) {
                
                // Einen Matchday (Spieltag) in DB anlegen
                $md_stmt = $db->prepare("INSERT INTO matchdays (event_id, matchday_number) VALUES (?, ?)");
                $md_stmt->execute([$event_id, $current_matchday]);
                $matchday_id = $db->lastInsertId();

                // Spiele für diese Runde anlegen
                for ($match = 0; $match < $matches_per_round; $match++) {
                    $home = ($round + $match) % ($num_teams - 1);
                    $away = ($num_teams - 1 - $match + $round) % ($num_teams - 1);

                    // Letztes Team in der Liste bleibt an Position 0 fest stehen
                    if ($match == 0) {
                        $away = $num_teams - 1;
                    }

                    // Hin- und Rückrunde tauschen Heim/Auswärts
                    if ($cycle == 1) {
                        $temp = $home; $home = $away; $away = $temp;
                    }

                    $home_team = $teams[$home];
                    $away_team = $teams[$away];

                    // Wenn eines der Teams das Ghost-Team ist, hat das andere Freilos (kein Match wird erstellt)
                    if ($home_team !== 'GHOST' && $away_team !== 'GHOST') {
                        $match_stmt = $db->prepare("INSERT INTO matches (matchday_id, team1_id, team2_id) VALUES (?, ?, ?)");
                        $match_stmt->execute([$matchday_id, $home_team, $away_team]);
                    }
                }
                
                $current_matchday++;
            }
        }

        echo json_encode(['success' => true, 'message' => 'Spielplan (' . ($current_matchday - 1) . ' Spieltage) erfolgreich generiert!']);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Datenbank-Fehler beim Generieren: ' . $e->getMessage()]);
    }
}
?>