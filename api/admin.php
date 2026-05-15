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
        $event_stmt = $db->query("SELECT id, duration_type FROM events WHERE status = 'active' LIMIT 1");
        $event = $event_stmt->fetch();

        if (!$event) {
            echo json_encode(['success' => false, 'message' => 'Es gibt kein aktives Event.']);
            exit;
        }

        $event_id = $event['id'];

        $teams_stmt = $db->prepare("SELECT id FROM teams WHERE event_id = ?");
        $teams_stmt->execute([$event_id]);
        $teams = $teams_stmt->fetchAll(PDO::FETCH_COLUMN);
        $num_teams = count($teams);

        if ($num_teams < 2) {
            echo json_encode(['success' => false, 'message' => 'Zu wenige Teams. Du brauchst mindestens 2.']);
            exit;
        }

        // Alten Spielplan löschen
        $db->prepare("DELETE FROM matchdays WHERE event_id = ?")->execute([$event_id]);

        $mode = $event['duration_type'];

        // ==========================================
        // MODUS: LIGA (Hin- und Rückrunde)
        // ==========================================
        if ($mode === 'lang' || $mode === 'standard') { // 'lang' ist dein altes "erweitert"
            $ghost = false;
            if ($num_teams % 2 != 0) {
                $teams[] = 'GHOST';
                $num_teams++;
                $ghost = true;
            }

            $total_rounds = $num_teams - 1;
            $matches_per_round = $num_teams / 2;
            $cycles = ($mode === 'lang') ? 2 : 1; // lang = Hin/Rück, standard = nur Hin
            $current_matchday = 1;

            for ($cycle = 0; $cycle < $cycles; $cycle++) {
                for ($round = 0; $round < $total_rounds; $round++) {
                    $md_stmt = $db->prepare("INSERT INTO matchdays (event_id, matchday_number) VALUES (?, ?)");
                    $md_stmt->execute([$event_id, $current_matchday]);
                    $matchday_id = $db->lastInsertId();

                    for ($match = 0; $match < $matches_per_round; $match++) {
                        $home = ($round + $match) % ($num_teams - 1);
                        $away = ($num_teams - 1 - $match + $round) % ($num_teams - 1);

                        if ($match == 0) $away = $num_teams - 1;
                        if ($cycle == 1) { $temp = $home; $home = $away; $away = $temp; }

                        $home_team = $teams[$home];
                        $away_team = $teams[$away];

                        if ($home_team !== 'GHOST' && $away_team !== 'GHOST') {
                            $db->prepare("INSERT INTO matches (matchday_id, team1_id, team2_id) VALUES (?, ?, ?)")
                               ->execute([$matchday_id, $home_team, $away_team]);
                        }
                    }
                    $current_matchday++;
                }
            }
            echo json_encode(['success' => true, 'message' => "Liga-Spielplan ($mode) erfolgreich generiert!"]);
        }
        
        // ==========================================
        // MODUS: ELIMINATION (K.O. System)
        // ==========================================
        elseif ($mode === 'kurz') { // 'kurz' ist jetzt unser Elimination-Modus
            
            if ($num_teams % 2 != 0) {
                echo json_encode(['success' => false, 'message' => 'Elimination benötigt eine GERADE Anzahl an Teams! (Hinweis: Am besten 4, 8 oder 16)']);
                exit;
            }

            // Teams mischen für zufällige Halbfinals
            shuffle($teams);

            if ($num_teams == 4) {
                // RUNDE 1: Halbfinale
                $db->prepare("INSERT INTO matchdays (event_id, matchday_number) VALUES (?, 1)")->execute([$event_id]);
                $md1_id = $db->lastInsertId();

                // Spiel 1 (A vs B)
                $stmt = $db->prepare("INSERT INTO matches (matchday_id, team1_id, team2_id) VALUES (?, ?, ?)");
                $stmt->execute([$md1_id, $teams[0], $teams[1]]);
                
                // Spiel 2 (C vs D)
                $stmt->execute([$md1_id, $teams[2], $teams[3]]);

                // RUNDE 2: Finale & Spiel um Platz 3 (Platzhalter)
                $db->prepare("INSERT INTO matchdays (event_id, matchday_number) VALUES (?, 2)")->execute([$event_id]);
                $md2_id = $db->lastInsertId();

                // Finale & Spiel um Platz 3 (team1_id und team2_id bleiben vorerst NULL!)
                $db->prepare("INSERT INTO matches (matchday_id, team1_id, team2_id) VALUES (?, NULL, NULL)")->execute([$md2_id]);
                $db->prepare("INSERT INTO matches (matchday_id, team1_id, team2_id) VALUES (?, NULL, NULL)")->execute([$md2_id]);

                echo json_encode(['success' => true, 'message' => 'Elimination-Baum für 4 Teams generiert! Halbfinals stehen fest, Finale wartet auf Ergebnisse.']);
            } 
            elseif ($num_teams == 6) {
                // Sonderfall: 6 Teams
                $db->prepare("INSERT INTO matchdays (event_id, matchday_number) VALUES (?, 1)")->execute([$event_id]);
                $md1_id = $db->lastInsertId();

                // 3 Duelle
                $db->prepare("INSERT INTO matches (matchday_id, team1_id, team2_id) VALUES (?, ?, ?)")->execute([$md1_id, $teams[0], $teams[1]]);
                $db->prepare("INSERT INTO matches (matchday_id, team1_id, team2_id) VALUES (?, ?, ?)")->execute([$md1_id, $teams[2], $teams[3]]);
                $db->prepare("INSERT INTO matches (matchday_id, team1_id, team2_id) VALUES (?, ?, ?)")->execute([$md1_id, $teams[4], $teams[5]]);

                // Spätere Runden müssen dynamisch per Admin-Button "Nächste Runde berechnen" gefüllt werden,
                // da wir die Gewinner/Verlierer abwarten müssen für die Mini-Liga.
                echo json_encode(['success' => true, 'message' => 'Elimination für 6 Teams gestartet. Erste Runde generiert (3 Duelle).']);
            } 
            else {
                echo json_encode(['success' => false, 'message' => 'Elimination für diese Anzahl an Teams (' . $num_teams . ') ist aktuell nicht unterstützt.']);
                exit;
            }
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Datenbank-Fehler: ' . $e->getMessage()]);
    }
}
?>