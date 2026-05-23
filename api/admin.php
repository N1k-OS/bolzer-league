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

        $teams_count = isset($_POST['teams_count']) ? max(2, (int)$_POST['teams_count']) : 4;

        if ($duration === 'kurz' && !ko_supported_team_count($teams_count)) {
            throw new Exception('Elimination (K.O.) benötigt genau 4, 8 oder 16 Teams.');
        }

        // 3. Teams für dieses Event anlegen
        $insert_team = $db->prepare("INSERT INTO teams (event_id, name, icon, budget) VALUES (?, ?, ?, 150)");
        
        $icons = ['🛡️', '🦁', '🦅', '🐍', '🐺', '🐻', '🦈', '🐅', '🦍', '🦅'];
        
        for ($i = 1; $i <= $teams_count; $i++) {
            $name = "Team $i";
            $icon = $icons[($i - 1) % count($icons)];
            $insert_team->execute([$new_event_id, $name, $icon]);
        }

        // 4. Den Admin (Dich) testweise in Team 1 stecken
        $stmt_alpha = $db->prepare("SELECT id FROM teams WHERE event_id = ? ORDER BY id ASC LIMIT 1");
        $stmt_alpha->execute([$new_event_id]);
        $alpha_id = $stmt_alpha->fetchColumn();
        $db->prepare("INSERT INTO rosters (event_id, team_id, user_id, current_category, current_price) VALUES (?, ?, ?, 'c', 50)")
           ->execute([$new_event_id, $alpha_id, $_SESSION['user_id']]);

        $schedule_msg = '';
        if ($duration === 'kurz') {
            $schedule = generate_ko_bracket($db, (int) $new_event_id, true);
        } else {
            $schedule = generate_league_schedule($db, (int) $new_event_id, $duration);
        }
        if (!$schedule['success']) {
            throw new Exception($schedule['message']);
        }
        $schedule_msg = ' ' . $schedule['message'];

        $db->commit();
        echo json_encode(['success' => true, 'message' => "Event '$name' erstellt! $teams_count Teams angelegt." . $schedule_msg]);

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
            $result = generate_ko_bracket($db, $event_id, true);
        } elseif ($mode === 'lang' || $mode === 'standard') {
            $result = generate_league_schedule($db, $event_id, $mode);
        } else {
            $result = ['success' => false, 'message' => 'Unbekannter Turnier-Modus.'];
        }

        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Datenbank-Fehler: ' . $e->getMessage()]);
    }
    }
    exit;
}

// ---------------------------------------------------------
// 1b. WM-KO GENERIEREN (Nur Standard-Liga)
// ---------------------------------------------------------
elseif ($action === 'generate_wm_ko') {
    try {
        $event_stmt = $db->query("SELECT id, duration_type FROM events WHERE status = 'active' LIMIT 1");
        $event = $event_stmt->fetch();

        if (!$event || $event['duration_type'] !== 'standard') {
            echo json_encode(['success' => false, 'message' => 'Nur im Standard-Modus (WM-Format) verfügbar.']);
            exit;
        }

        echo json_encode(generate_wm_ko_bracket($db, (int) $event['id']));
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
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

        echo json_encode(repair_ko_bracket($db, (int) $event['id']));
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

// ---------------------------------------------------------
// 6. ADMIN: ZUFÄLLIGE TEAM-EINTEILUNG (FÜR SPIELER OHNE TEAM)
// ---------------------------------------------------------
elseif ($action === 'distribute_players_random') {
    try {
        $event_stmt = $db->query("SELECT id FROM events WHERE status = 'active' LIMIT 1");
        $event = $event_stmt->fetch();
        if (!$event) {
            echo json_encode(['success' => false, 'message' => 'Kein aktives Event.']);
            exit;
        }
        $event_id = (int) $event['id'];

        // Alle Teams des Events holen
        $teams_stmt = $db->prepare("SELECT id FROM teams WHERE event_id = ? ORDER BY id ASC");
        $teams_stmt->execute([$event_id]);
        $teams = $teams_stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($teams)) {
            echo json_encode(['success' => false, 'message' => 'Es gibt keine Teams für dieses Event.']);
            exit;
        }

        // Alle User holen, die noch nicht in DIESEM Event in einem Team sind
        // Ignoriere reine Admins (wenn wir wollen, dass Admins nicht mitspielen, aber lassen wir sie fürs erste mitspielen, wenn sie sich registrieren)
        $free_users_stmt = $db->prepare("
            SELECT u.id, u.base_category 
            FROM users u 
            LEFT JOIN rosters r ON u.id = r.user_id AND r.event_id = ?
            WHERE r.id IS NULL
        ");
        $free_users_stmt->execute([$event_id]);
        $free_users = $free_users_stmt->fetchAll();

        if (empty($free_users)) {
            echo json_encode(['success' => false, 'message' => 'Alle Spieler sind bereits in Teams eingeteilt.']);
            exit;
        }

        // Sortiere Spieler nach Kategorie, um faire Verteilung zu fördern
        usort($free_users, function($a, $b) {
            return strcmp($a['base_category'], $b['base_category']);
        });

        // Ein bisschen Zufall reinbringen, aber innerhalb der Kategorien
        // Wir mischen nicht komplett durch, sondern verteilen sie nacheinander (Round Robin)
        // Aber um es nicht vorhersehbar zu machen, mischen wir die Teams-Reihenfolge
        shuffle($teams);
        
        $insert_roster = $db->prepare("INSERT INTO rosters (event_id, team_id, user_id, current_category, current_price) VALUES (?, ?, ?, ?, 50)");
        
        $db->beginTransaction();
        
        $team_index = 0;
        $num_teams = count($teams);
        $count = 0;
        
        foreach ($free_users as $user) {
            $insert_roster->execute([$event_id, $teams[$team_index], $user['id'], $user['base_category']]);
            $team_index = ($team_index + 1) % $num_teams;
            $count++;
        }
        
        $db->commit();
        echo json_encode(['success' => true, 'message' => "$count Spieler wurden zufällig verteilt!"]);
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }
}

// ---------------------------------------------------------
// 7. ADMIN: MANUELLE TEAM-EINTEILUNG
// ---------------------------------------------------------
elseif ($action === 'distribute_players_manual') {
    $assignments_json = $_POST['assignments'] ?? '[]';
    $assignments = json_decode($assignments_json, true);

    if (empty($assignments)) {
        echo json_encode(['success' => false, 'message' => 'Keine Zuweisungen empfangen.']);
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

        $db->beginTransaction();
        
        $insert_roster = $db->prepare("INSERT INTO rosters (event_id, team_id, user_id, current_category, current_price) VALUES (?, ?, ?, ?, 50)");
        $get_cat = $db->prepare("SELECT base_category FROM users WHERE id = ?");
        
        $count = 0;
        foreach ($assignments as $a) {
            $user_id = (int) $a['user_id'];
            $team_id = (int) $a['team_id'];
            
            if ($team_id <= 0) continue; // Überspringen, falls "Kein Team" gewählt

            // Prüfen, ob User schon ein Team hat in diesem Event
            $check = $db->prepare("SELECT id FROM rosters WHERE event_id = ? AND user_id = ?");
            $check->execute([$event_id, $user_id]);
            if ($check->rowCount() > 0) continue;

            $get_cat->execute([$user_id]);
            $cat = $get_cat->fetchColumn() ?: 'c';

            $insert_roster->execute([$event_id, $team_id, $user_id, $cat]);
            $count++;
        }
        
        $db->commit();
        echo json_encode(['success' => true, 'message' => "$count Spieler wurden manuell zugewiesen!"]);

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }
}
?>