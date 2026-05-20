<?php
// api/admin.php
session_start();
require_once '../includes/db.php';
require_once '../includes/tournament.php';
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
        $stmt_alpha = $db->prepare("SELECT id FROM teams WHERE event_id = ? ORDER BY id ASC LIMIT 1");
        $stmt_alpha->execute([$new_event_id]);
        $alpha_id = $stmt_alpha->fetchColumn();
        $db->prepare("INSERT INTO rosters (event_id, team_id, user_id, current_category, current_price) VALUES (?, ?, ?, 'c', 50)")
           ->execute([$new_event_id, $alpha_id, $_SESSION['user_id']]);

        $schedule_msg = '';
        if ($duration === 'kurz') {
            $schedule = generate_ko_bracket_4($db, (int) $new_event_id, true);
        } else {
            $schedule = generate_league_schedule($db, (int) $new_event_id, $duration);
        }
        if (!$schedule['success']) {
            throw new Exception($schedule['message']);
        }
        $schedule_msg = ' ' . $schedule['message'];

        $db->commit();
        echo json_encode(['success' => true, 'message' => "Event '$name' erstellt! 4 Teams angelegt." . $schedule_msg]);

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

        $event_id = (int) $event['id'];
        $mode = $event['duration_type'];

        if ($mode === 'kurz') {
            $result = generate_ko_bracket_4($db, $event_id, true);
        } elseif ($mode === 'lang' || $mode === 'standard') {
            $result = generate_league_schedule($db, $event_id, $mode);
        } else {
            $result = ['success' => false, 'message' => 'Unbekannter Turnier-Modus.'];
        }

        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Datenbank-Fehler: ' . $e->getMessage()]);
    }
    exit;
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
        $event_id = (int) $event['id'];

        $tc_stmt = $db->prepare('SELECT COUNT(*) FROM teams WHERE event_id = ?');
        $tc_stmt->execute([$event_id]);
        $team_count = (int) $tc_stmt->fetchColumn();

        if ($team_count === 4) {
            echo json_encode(repair_ko_bracket_4($db, $event_id));
            exit;
        }

        // Finde den LETZTEN Spieltag (Legacy: größere K.O.-Felder)
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

        advance_ko_after_result($db, (int) $match_id, (int) $score1, (int) $score2);

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Ergebnis gespeichert. Bracket und Begegnungen wurden aktualisiert.']);

    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }
}

// ---------------------------------------------------------
// 4. AKTUELLES EVENT BEENDEN
// ---------------------------------------------------------
elseif ($action === 'end_event') {
    try {
        $stmt = $db->prepare("UPDATE events SET status = 'finished' WHERE status = 'active'");
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Kein aktives Event gefunden.']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Das aktuelle Event wurde beendet.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }
}

// ---------------------------------------------------------
// 5. ADMIN: SPIELER IN ANDERES TEAM VERSCHIEBEN (OVERRIDE)
// ---------------------------------------------------------
elseif ($action === 'force_transfer') {
    $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    $target_team_id = isset($_POST['target_team_id']) ? (int) $_POST['target_team_id'] : 0;

    if ($user_id <= 0 || $target_team_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Spieler und Zielteam sind erforderlich.']);
        exit;
    }

    try {
        $event_stmt = $db->query("SELECT id FROM events WHERE status = 'active' LIMIT 1");
        $event = $event_stmt->fetch();
        if (!$event) {
            echo json_encode(['success' => false, 'message' => 'Kein aktives Event.']);
            exit;
        }
        $event_id = (int) $event['id'];

        $check_player = $db->prepare('SELECT team_id FROM rosters WHERE event_id = ? AND user_id = ?');
        $check_player->execute([$event_id, $user_id]);
        $row = $check_player->fetch();
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Spieler ist in diesem Event nicht im Kader.']);
            exit;
        }
        $from_team_id = (int) $row['team_id'];
        if ($from_team_id === $target_team_id) {
            echo json_encode(['success' => false, 'message' => 'Zielteam ist bereits das aktuelle Team.']);
            exit;
        }

        $check_team = $db->prepare('SELECT id FROM teams WHERE id = ? AND event_id = ?');
        $check_team->execute([$target_team_id, $event_id]);
        if (!$check_team->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Zielteam gehört nicht zum aktiven Event.']);
            exit;
        }

        $upd = $db->prepare('UPDATE rosters SET team_id = ? WHERE event_id = ? AND user_id = ?');
        $upd->execute([$target_team_id, $event_id, $user_id]);

        echo json_encode(['success' => true, 'message' => 'Spieler wurde in das gewählte Team verschoben.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }
}
?>