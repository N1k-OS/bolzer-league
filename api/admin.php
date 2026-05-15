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
// 1. SPIELPLAN GENERIEREN (Runde 1)
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
                echo json_encode(['success' => false, 'message' => 'Elimination benötigt eine GERADE Anzahl an Teams!']);
                exit;
            }

            shuffle($teams);

            // Wir erstellen IMMER nur Runde 1. Egal ob 4 oder 6 Teams.
            $db->prepare("INSERT INTO matchdays (event_id, matchday_number) VALUES (?, 1)")->execute([$event_id]);
            $md1_id = $db->lastInsertId();

            $stmt = $db->prepare("INSERT INTO matches (matchday_id, team1_id, team2_id) VALUES (?, ?, ?)");
            
            // Paarungen (immer 2 Teams gegeneinander)
            for ($i = 0; $i < $num_teams; $i += 2) {
                $stmt->execute([$md1_id, $teams[$i], $teams[$i+1]]);
            }

            echo json_encode(['success' => true, 'message' => "K.O.-Baum gestartet! Runde 1 mit " . ($num_teams/2) . " Duellen generiert."]);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Datenbank-Fehler: ' . $e->getMessage()]);
    }
}

// ---------------------------------------------------------
// 2. NÄCHSTE RUNDE BERECHNEN (Nur K.O.)
// ---------------------------------------------------------
elseif ($action === 'calculate_next_round') {
    try {
        $event_stmt = $db->query("SELECT id, duration_type FROM events WHERE status = 'active' LIMIT 1");
        $event = $event_stmt->fetch();

        if (!$event || $event['duration_type'] !== 'kurz') {
            echo json_encode(['success' => false, 'message' => 'Nur im Elimination-Modus (K.O.) verfügbar.']);
            exit;
        }
        $event_id = $event['id'];

        // Finde den LETZTEN Spieltag
        $md_stmt = $db->prepare("SELECT id, matchday_number FROM matchdays WHERE event_id = ? ORDER BY matchday_number DESC LIMIT 1");
        $md_stmt->execute([$event_id]);
        $last_md = $md_stmt->fetch();

        if (!$last_md) {
            echo json_encode(['success' => false, 'message' => 'Noch kein Spielplan generiert.']);
            exit;
        }

        // Prüfen, ob alle Spiele des letzten Spieltags beendet sind
        $matches_stmt = $db->prepare("SELECT id, team1_id, team2_id, score1, score2, status FROM matches WHERE matchday_id = ?");
        $matches_stmt->execute([$last_md['id']]);
        $last_matches = $matches_stmt->fetchAll();

        $winners = [];
        $losers = [];
        foreach ($last_matches as $m) {
            if ($m['status'] !== 'finished') {
                echo json_encode(['success' => false, 'message' => 'Es sind noch nicht alle Spiele der aktuellen Runde beendet!']);
                exit;
            }
            if ($m['score1'] > $m['score2'] || $m['score1'] == $m['score2']) {
                $winners[] = $m['team1_id'];
                $losers[] = $m['team2_id'];
            } else {
                $winners[] = $m['team2_id'];
                $losers[] = $m['team1_id'];
            }
        }

        // NEUE RUNDE ERSTELLEN
        $next_md_num = $last_md['matchday_number'] + 1;
        $db->prepare("INSERT INTO matchdays (event_id, matchday_number) VALUES (?, ?)")->execute([$event_id, $next_md_num]);
        $new_md_id = $db->lastInsertId();

        $create_match = $db->prepare("INSERT INTO matches (matchday_id, team1_id, team2_id) VALUES (?, ?, ?)");
        $num_winners = count($winners);

        // FALL A: 2 Gewinner (Finale)
        if ($num_winners == 2) {
            $create_match->execute([$new_md_id, $winners[0], $winners[1]]); // Finale
            $create_match->execute([$new_md_id, $losers[0], $losers[1]]); // Platz 3
            echo json_encode(['success' => true, 'message' => "Finale generiert!"]);
        }
        // FALL B: 4, 8 Gewinner (Normale K.O. Runde)
        elseif ($num_winners % 2 == 0) {
            for ($i = 0; $i < $num_winners; $i += 2) {
                $create_match->execute([$new_md_id, $winners[$i], $winners[$i+1]]);
            }
            // Verlierer gegeneinander (Platzierung)
            for ($i = 0; $i < count($losers); $i += 2) {
                $create_match->execute([$new_md_id, $losers[$i], $losers[$i+1]]);
            }
            echo json_encode(['success' => true, 'message' => "Nächste K.O.-Runde generiert!"]);
        } 
        // FALL C: 3 Gewinner (6-Teams Sonderfall)
        elseif ($num_winners == 3) {
            // Gewinner (Mini-Liga)
            $create_match->execute([$new_md_id, $winners[0], $winners[1]]);
            $create_match->execute([$new_md_id, $winners[1], $winners[2]]);
            $create_match->execute([$new_md_id, $winners[0], $winners[2]]);
            // Verlierer (Mini-Liga)
            $create_match->execute([$new_md_id, $losers[0], $losers[1]]);
            $create_match->execute([$new_md_id, $losers[1], $losers[2]]);
            $create_match->execute([$new_md_id, $losers[0], $losers[2]]);
            echo json_encode(['success' => true, 'message' => "Mini-Ligen für Platz 1-3 und 4-6 generiert!"]);
        } else {
            // Wenn man die Mini-Liga bei 6 Teams beendet hat, gibt es 0 Gewinner, die ein K.O.-System weiterführen können
            echo json_encode(['success' => false, 'message' => "Das Turnier ist beendet. Es kann keine weitere Runde generiert werden."]);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }
}

// ---------------------------------------------------------
// 3. SPIEL ERGEBNIS MANUELL EINTRAGEN
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
        // Hier tragen wir NUR die Tore ein. K.O.-Logik passiert in Action 2!
        $update_stmt = $db->prepare("UPDATE matches SET score1 = ?, score2 = ?, status = 'finished' WHERE id = ?");
        $update_stmt->execute([$score1, $score2, $match_id]);

        echo json_encode(['success' => true, 'message' => 'Ergebnis gespeichert!']);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }
}
?>