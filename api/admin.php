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
// 0. NEUES EVENT ERSTELLEN
// ---------------------------------------------------------
if ($action === 'create_event') {
    $name = $_POST['name'] ?? '';
    $duration = $_POST['duration'] ?? 'standard';

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Bitte einen Namen eingeben.']);
        exit;
    }

    try {
        $db->beginTransaction();

        // 1. Alle alten Events auf "finished" setzen (Archivieren)
        $db->exec("UPDATE events SET status = 'finished' WHERE status = 'active' OR status = 'upcoming'");

        // 2. Neues Event erstellen
        $stmt = $db->prepare("INSERT INTO events (name, event_date, duration_type, status, created_by) VALUES (?, CURDATE(), ?, 'active', ?)");
        $stmt->execute([$name, $duration, $_SESSION['user_id']]);
        $new_event_id = $db->lastInsertId();

        // 3. Dummy-Teams für dieses Event anlegen (damit man sofort testen kann)
        $teams = [
            ['Team Alpha', '🛡️'],
            ['Team Bravo', '🦁'],
            ['Team Charlie', '🦅'],
            ['Team Delta', '🐍']
        ];
        $insert_team = $db->prepare("INSERT INTO teams (event_id, name, icon, budget) VALUES (?, ?, ?, 150)");
        foreach ($teams as $t) {
            $insert_team->execute([$new_event_id, $t[0], $t[1]]);
        }

        // 4. Den Admin (Dich) testweise in Team Alpha stecken
        $alpha_id = $db->query("SELECT id FROM teams WHERE event_id = $new_event_id ORDER BY id ASC LIMIT 1")->fetchColumn();
        $db->prepare("INSERT INTO rosters (event_id, team_id, user_id, current_category, current_price) VALUES (?, ?, ?, 'c', 50)")
           ->execute([$new_event_id, $alpha_id, $_SESSION['user_id']]);

        $db->commit();
        echo json_encode(['success' => true, 'message' => "Event '$name' ($duration) erstellt! 4 Teams wurden hinzugefügt."]);

    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }
    exit; // Wichtig: Bricht hier ab
}

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

            if ($num_teams == 4) {
                // RUNDE 1: Halbfinale
                $db->prepare("INSERT INTO matchdays (event_id, matchday_number) VALUES (?, 1)")->execute([$event_id]);
                $md1_id = $db->lastInsertId();

                $stmt = $db->prepare("INSERT INTO matches (matchday_id, team1_id, team2_id) VALUES (?, ?, ?)");
                $stmt->execute([$md1_id, $teams[0], $teams[1]]); // Spiel 1
                $stmt->execute([$md1_id, $teams[2], $teams[3]]); // Spiel 2

                // RUNDE 2: Finale & Spiel um Platz 3 (Platzhalter)
                $db->prepare("INSERT INTO matchdays (event_id, matchday_number) VALUES (?, 2)")->execute([$event_id]);
                $md2_id = $db->lastInsertId();

                // NULL erlaubt, wenn die Tabellenstruktur vorhin angepasst wurde!
                $db->prepare("INSERT INTO matches (matchday_id, team1_id, team2_id) VALUES (?, NULL, NULL)")->execute([$md2_id]); // Finale
                $db->prepare("INSERT INTO matches (matchday_id, team1_id, team2_id) VALUES (?, NULL, NULL)")->execute([$md2_id]); // Platz 3

                echo json_encode(['success' => true, 'message' => "Elimination-Baum für 4 Teams komplett aufgebaut! Halbfinals stehen, Finale ist TBD."]);
            } else {
                echo json_encode(['success' => false, 'message' => "Elimination ist aktuell nur für 4 Teams vollautomatisiert implementiert. Du hast $num_teams Teams."]);
            }
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
// 3. SPIEL ERGEBNIS MANUELL EINTRAGEN & TURNIERBAUM UPDATE
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

        $update_stmt = $db->prepare("UPDATE matches SET score1 = ?, score2 = ?, status = 'finished' WHERE id = ?");
        $update_stmt->execute([$score1, $score2, $match_id]);

        // K.O.-Automatik: Prüfen, ob wir im Elimination-Modus (Halbfinale) sind
        $match_info = $db->prepare("
            SELECT m.team1_id, m.team2_id, md.event_id, md.matchday_number, e.duration_type 
            FROM matches m 
            JOIN matchdays md ON m.matchday_id = md.id 
            JOIN events e ON md.event_id = e.id 
            WHERE m.id = ?
        ");
        $match_info->execute([$match_id]);
        $info = $match_info->fetch();

        if ($info['duration_type'] === 'kurz' && $info['matchday_number'] == 1) {
            
            $winner_id = ($score1 >= $score2) ? $info['team1_id'] : $info['team2_id']; // Bei Unentschieden gewinnt T1
            $loser_id = ($score1 >= $score2) ? $info['team2_id'] : $info['team1_id'];

            // Hole die beiden Platzhalter-Spiele von Spieltag 2 (Finale & Platz 3)
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

                // Hilfsfunktion: Füllt den nächsten freien Slot (team1 oder team2) in einem Spiel
                $fill_slot = function($match_to_fill_id, $team_id_to_insert) use ($db) {
                    $check = $db->prepare("SELECT team1_id, team2_id FROM matches WHERE id = ?");
                    $check->execute([$match_to_fill_id]);
                    $m = $check->fetch();

                    if (is_null($m['team1_id'])) {
                        $db->prepare("UPDATE matches SET team1_id = ? WHERE id = ?")->execute([$team_id_to_insert, $match_to_fill_id]);
                    } elseif (is_null($m['team2_id'])) {
                        $db->prepare("UPDATE matches SET team2_id = ? WHERE id = ?")->execute([$team_id_to_insert, $match_to_fill_id]);
                    }
                };

                // Gewinner ins Finale (Slot 1), Verlierer in Platz 3 (Slot 2)
                $fill_slot($finale_id, $winner_id);
                $fill_slot($platz3_id, $loser_id);
            }
        }

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Ergebnis gespeichert! (Turnierbaum aktualisiert)']);

    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }
}
?>