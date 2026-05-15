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
        if ($mode === 'lang' || $mode === 'standard') {
            $ghost = false;
            if ($num_teams % 2 != 0) {
                $teams[] = 'GHOST';
                $num_teams++;
                $ghost = true;
            }

            $total_rounds = $num_teams - 1;
            $matches_per_round = $num_teams / 2;
            $cycles = ($mode === 'lang') ? 2 : 1; 
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
        elseif ($mode === 'kurz') { 
            
            if ($num_teams % 2 != 0) {
                echo json_encode(['success' => false, 'message' => 'Elimination benötigt eine GERADE Anzahl an Teams! (Hinweis: Am besten 4, 8 oder 16)']);
                exit;
            }

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

                // FIX: RUNDE 2 ERSTELLEN, bevor wir $md2_id nutzen!
                $db->prepare("INSERT INTO matchdays (event_id, matchday_number) VALUES (?, 2)")->execute([$event_id]);
                $md2_id = $db->lastInsertId();

                // Finale & Spiel um Platz 3 
                $db->prepare("INSERT INTO matches (matchday_id, team1_id, team2_id) VALUES (?, NULL, NULL)")->execute([$md2_id]);
                $db->prepare("INSERT INTO matches (matchday_id, team1_id, team2_id) VALUES (?, NULL, NULL)")->execute([$md2_id]);

                echo json_encode(['success' => true, 'message' => 'Elimination-Baum für 4 Teams generiert! Halbfinals stehen fest, Finale wartet auf Ergebnisse.']);
            } 
            elseif ($num_teams == 6) {
                $db->prepare("INSERT INTO matchdays (event_id, matchday_number) VALUES (?, 1)")->execute([$event_id]);
                $md1_id = $db->lastInsertId();

                $db->prepare("INSERT INTO matches (matchday_id, team1_id, team2_id) VALUES (?, ?, ?)")->execute([$md1_id, $teams[0], $teams[1]]);
                $db->prepare("INSERT INTO matches (matchday_id, team1_id, team2_id) VALUES (?, ?, ?)")->execute([$md1_id, $teams[2], $teams[3]]);
                $db->prepare("INSERT INTO matches (matchday_id, team1_id, team2_id) VALUES (?, ?, ?)")->execute([$md1_id, $teams[4], $teams[5]]);

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
// NÄCHSTE RUNDE BERECHNEN (Nur für Elimination/kurz)
// ---------------------------------------------------------
elseif ($action === 'calculate_next_round') {
    try {
        $event_stmt = $db->query("SELECT id, duration_type FROM events WHERE status = 'active' LIMIT 1");
        $event = $event_stmt->fetch();

        if (!$event || $event['duration_type'] !== 'kurz') {
            echo json_encode(['success' => false, 'message' => 'Nur im Elimination-Modus verfügbar.']);
            exit;
        }
        $event_id = $event['id'];

        // 1. Finde den LETZTEN Spieltag heraus
        $md_stmt = $db->prepare("SELECT id, matchday_number FROM matchdays WHERE event_id = ? ORDER BY matchday_number DESC LIMIT 1");
        $md_stmt->execute([$event_id]);
        $last_md = $md_stmt->fetch();

        if (!$last_md) {
            echo json_encode(['success' => false, 'message' => 'Noch kein Spielplan generiert.']);
            exit;
        }

        // 2. Prüfen, ob alle Spiele des letzten Spieltags beendet sind
        $matches_stmt = $db->prepare("SELECT id, team1_id, team2_id, score1, score2, status FROM matches WHERE matchday_id = ?");
        $matches_stmt->execute([$last_md['id']]);
        $last_matches = $matches_stmt->fetchAll();

        foreach ($last_matches as $m) {
            if ($m['status'] !== 'finished') {
                echo json_encode(['success' => false, 'message' => 'Es sind noch nicht alle Spiele der aktuellen Runde beendet!']);
                exit;
            }
        }

        // 3. Gewinner und Verlierer der letzten Runde ermitteln
        $winners = [];
        $losers = [];
        foreach ($last_matches as $m) {
            if ($m['score1'] > $m['score2'] || $m['score1'] == $m['score2']) { // Bei Unentschieden gewinnt vorerst Team 1
                $winners[] = $m['team1_id'];
                $losers[] = $m['team2_id'];
            } else {
                $winners[] = $m['team2_id'];
                $losers[] = $m['team1_id'];
            }
        }

        // 4. NEUE RUNDE ERSTELLEN
        $next_md_num = $last_md['matchday_number'] + 1;
        $db->prepare("INSERT INTO matchdays (event_id, matchday_number) VALUES (?, ?)")->execute([$event_id, $next_md_num]);
        $new_md_id = $db->lastInsertId();

        // 5. PAARUNGEN ERSTELLEN
        $create_match = $db->prepare("INSERT INTO matches (matchday_id, team1_id, team2_id) VALUES (?, ?, ?)");

        $num_winners = count($winners);

        // FALL A: Normale K.O. Runde (Potenzen von 2: 2, 4, 8 Gewinner)
        if ($num_winners % 2 == 0) {
            // Gewinner spielen gegeneinander (nächstes Halbfinale/Finale)
            for ($i = 0; $i < $num_winners; $i += 2) {
                $create_match->execute([$new_md_id, $winners[$i], $winners[$i+1]]);
            }
            // Verlierer spielen gegeneinander (Platzierungsspiele)
            for ($i = 0; $i < count($losers); $i += 2) {
                $create_match->execute([$new_md_id, $losers[$i], $losers[$i+1]]);
            }
            echo json_encode(['success' => true, 'message' => "Runde $next_md_num generiert!"]);
        } 
        // FALL B: Der 6-Teams Sonderfall (3 Gewinner, 3 Verlierer)
        elseif ($num_winners == 3) {
            // Wir erstellen die "Mini-Liga" (A-B, B-C, A-C) für Gewinner und Verlierer
            // Gewinner (Platz 1-3)
            $create_match->execute([$new_md_id, $winners[0], $winners[1]]);
            $create_match->execute([$new_md_id, $winners[1], $winners[2]]);
            $create_match->execute([$new_md_id, $winners[0], $winners[2]]);
            
            // Verlierer (Platz 4-6)
            $create_match->execute([$new_md_id, $losers[0], $losers[1]]);
            $create_match->execute([$new_md_id, $losers[1], $losers[2]]);
            $create_match->execute([$new_md_id, $losers[0], $losers[2]]);
            
            echo json_encode(['success' => true, 'message' => "Runde $next_md_num (Mini-Liga für 6 Teams) generiert!"]);
        } 
        else {
            echo json_encode(['success' => false, 'message' => "System kann für $num_winners verbleibende Teams keinen Plan berechnen."]);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
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

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Ergebnis gespeichert!']);

    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }
}
?>