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

// ---------------------------------------------------------
// SPIEL ERGEBNIS MANUELL EINTRAGEN & TURNIERBAUM UPDATE
// ---------------------------------------------------------
elseif ($action === 'submit_result') {
    $match_id = $_POST['match_id'] ?? 0;
    $score1 = $_POST['score1'] ?? 0;
    $score2 = $_POST['score2'] ?? 0;

    if (!$match_id) {
        echo json_encode(['success' => false, 'message' => 'Kein Spiel ausgewählt.']);
        exit;
    }

    try {
        $db->beginTransaction();

        // 1. Das Spiel auf 'finished' setzen und Tore eintragen
        $update_stmt = $db->prepare("UPDATE matches SET score1 = ?, score2 = ?, status = 'finished' WHERE id = ?");
        $update_stmt->execute([$score1, $score2, $match_id]);

        // 2. Herausfinden, zu welchem Event und welchem Modus dieses Spiel gehört
        $match_info = $db->prepare("
            SELECT m.team1_id, m.team2_id, md.event_id, md.matchday_number, e.duration_type 
            FROM matches m 
            JOIN matchdays md ON m.matchday_id = md.id 
            JOIN events e ON md.event_id = e.id 
            WHERE m.id = ?
        ");
        $match_info->execute([$match_id]);
        $info = $match_info->fetch();

        // 3. ELIMINATION-LOGIK (Nur bei 'kurz' und Spieltag 1 / Halbfinale relevant!)
        if ($info['duration_type'] === 'kurz' && $info['matchday_number'] == 1) {
            
            // Wer hat gewonnen?
            $winner_id = ($score1 > $score2) ? $info['team1_id'] : $info['team2_id'];
            $loser_id = ($score1 > $score2) ? $info['team2_id'] : $info['team1_id'];
            
            // Bei Unentschieden im K.O. entscheidet aktuell Team 1 (kann später durch Elfmeterschießen ersetzt werden)
            if ($score1 == $score2) {
                $winner_id = $info['team1_id']; 
                $loser_id = $info['team2_id'];
            }

            // Hole die Platzhalter-Spiele von Spieltag 2 (Finale & Platz 3)
            $md2_stmt = $db->prepare("
                SELECT m.id 
                FROM matches m 
                JOIN matchdays md ON m.matchday_id = md.id 
                WHERE md.event_id = ? AND md.matchday_number = 2 
                ORDER BY m.id ASC
            ");
            $md2_stmt->execute([$info['event_id']]);
            $finals = $md2_stmt->fetchAll();

            if (count($finals) == 2) {
                $finale_id = $finals[0]['id'];
                $platz3_id = $finals[1]['id'];

                // Funktion, um das erste freie NULL-Feld in einem Match zu füllen
                function fillBracketSlot($db, $match_id, $team_id) {
                    $check = $db->prepare("SELECT team1_id, team2_id FROM matches WHERE id = ?");
                    $check->execute([$match_id]);
                    $m = $check->fetch();

                    if (is_null($m['team1_id'])) {
                        $db->prepare("UPDATE matches SET team1_id = ? WHERE id = ?")->execute([$team_id, $match_id]);
                    } elseif (is_null($m['team2_id'])) {
                        $db->prepare("UPDATE matches SET team2_id = ? WHERE id = ?")->execute([$team_id, $match_id]);
                    }
                }

                // Gewinner ins Finale schieben, Verlierer ins Spiel um Platz 3
                fillBracketSlot($db, $finale_id, $winner_id);
                fillBracketSlot($db, $platz3_id, $loser_id);
            }
        }

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Ergebnis gespeichert!']);

    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }
}
?>