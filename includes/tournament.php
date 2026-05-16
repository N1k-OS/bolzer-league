<?php
/**
 * Turnier-Logik: Spielplan-Erstellung und K.O.-Fortschreibung.
 * Normaler Datenfluss später per Umfragen; Admin-Aktionen sind Fallback.
 */

function generate_league_schedule(PDO $db, int $event_id, string $mode): array
{
    if ($mode !== 'lang' && $mode !== 'standard') {
        return ['success' => false, 'message' => 'Ungültiger Liga-Modus.'];
    }

    $teams_stmt = $db->prepare('SELECT id FROM teams WHERE event_id = ?');
    $teams_stmt->execute([$event_id]);
    $teams = $teams_stmt->fetchAll(PDO::FETCH_COLUMN);
    $num_teams = count($teams);

    if ($num_teams < 2) {
        return ['success' => false, 'message' => 'Zu wenige Teams. Mindestens 2 erforderlich.'];
    }

    $db->prepare('DELETE FROM matchdays WHERE event_id = ?')->execute([$event_id]);

    if ($num_teams % 2 !== 0) {
        $teams[] = 'GHOST';
        $num_teams++;
    }

    $total_rounds = $num_teams - 1;
    $matches_per_round = (int) ($num_teams / 2);
    $cycles = ($mode === 'lang') ? 2 : 1;
    $current_matchday = 1;

    for ($cycle = 0; $cycle < $cycles; $cycle++) {
        for ($round = 0; $round < $total_rounds; $round++) {
            $md_stmt = $db->prepare('INSERT INTO matchdays (event_id, matchday_number) VALUES (?, ?)');
            $md_stmt->execute([$event_id, $current_matchday]);
            $matchday_id = $db->lastInsertId();

            for ($match = 0; $match < $matches_per_round; $match++) {
                $home = ($round + $match) % ($num_teams - 1);
                $away = ($num_teams - 1 - $match + $round) % ($num_teams - 1);

                if ($match === 0) {
                    $away = $num_teams - 1;
                }
                if ($cycle === 1) {
                    $temp = $home;
                    $home = $away;
                    $away = $temp;
                }

                $home_team = $teams[$home];
                $away_team = $teams[$away];

                if ($home_team !== 'GHOST' && $away_team !== 'GHOST') {
                    $db->prepare('INSERT INTO matches (matchday_id, team1_id, team2_id) VALUES (?, ?, ?)')
                        ->execute([$matchday_id, $home_team, $away_team]);
                }
            }
            $current_matchday++;
        }
    }

    return ['success' => true, 'message' => "Liga-Spielplan ($mode) angelegt."];
}

function generate_ko_bracket_4(PDO $db, int $event_id, bool $shuffle = true): array
{
    $teams_stmt = $db->prepare('SELECT id FROM teams WHERE event_id = ? ORDER BY id');
    $teams_stmt->execute([$event_id]);
    $teams = $teams_stmt->fetchAll(PDO::FETCH_COLUMN);
    $num_teams = count($teams);

    if ($num_teams !== 4) {
        return [
            'success' => false,
            'message' => "Elimination (4 Teams) benötigt genau 4 Teams, aktuell: $num_teams.",
        ];
    }

    $db->prepare('DELETE FROM matchdays WHERE event_id = ?')->execute([$event_id]);

    if ($shuffle) {
        shuffle($teams);
    }

    $db->prepare('INSERT INTO matchdays (event_id, matchday_number) VALUES (?, 1)')->execute([$event_id]);
    $md1_id = (int) $db->lastInsertId();

    $stmt = $db->prepare('INSERT INTO matches (matchday_id, team1_id, team2_id, status) VALUES (?, ?, ?, ?)');
    $stmt->execute([$md1_id, $teams[0], $teams[1], 'upcoming']);
    $stmt->execute([$md1_id, $teams[2], $teams[3], 'upcoming']);

    $db->prepare('INSERT INTO matchdays (event_id, matchday_number) VALUES (?, 2)')->execute([$event_id]);
    $md2_id = (int) $db->lastInsertId();

    $placeholder = $db->prepare('INSERT INTO matches (matchday_id, team1_id, team2_id, status) VALUES (?, NULL, NULL, ?)');
    $placeholder->execute([$md2_id, 'upcoming']);
    $placeholder->execute([$md2_id, 'upcoming']);

    return [
        'success' => true,
        'message' => 'K.O.-Baum angelegt (Halbfinale + Finale/Platz 3 mit TBA-Plätzen).',
    ];
}

function fill_elimination_slot(PDO $db, int $match_id, int $team_id): void
{
    $check = $db->prepare('SELECT team1_id, team2_id FROM matches WHERE id = ?');
    $check->execute([$match_id]);
    $m = $check->fetch(PDO::FETCH_ASSOC);

    if (!$m) {
        return;
    }

    if ($m['team1_id'] === null) {
        $db->prepare('UPDATE matches SET team1_id = ? WHERE id = ?')->execute([$team_id, $match_id]);
    } elseif ($m['team2_id'] === null) {
        $db->prepare('UPDATE matches SET team2_id = ? WHERE id = ?')->execute([$team_id, $match_id]);
    }
}

function advance_ko_after_result(PDO $db, int $match_id, int $score1, int $score2): void
{
    $match_info = $db->prepare("
        SELECT m.team1_id, m.team2_id, md.event_id, md.matchday_number, e.duration_type
        FROM matches m
        JOIN matchdays md ON m.matchday_id = md.id
        JOIN events e ON md.event_id = e.id
        WHERE m.id = ?
    ");
    $match_info->execute([$match_id]);
    $info = $match_info->fetch(PDO::FETCH_ASSOC);

    if (!$info || $info['duration_type'] !== 'kurz') {
        return;
    }

    if ($info['team1_id'] === null || $info['team2_id'] === null) {
        return;
    }

    if ((int) $info['matchday_number'] !== 1) {
        return;
    }

    $winner_id = ($score1 >= $score2) ? (int) $info['team1_id'] : (int) $info['team2_id'];
    $loser_id = ($score1 >= $score2) ? (int) $info['team2_id'] : (int) $info['team1_id'];

    $md2_stmt = $db->prepare("
        SELECT m.id
        FROM matches m
        JOIN matchdays md ON m.matchday_id = md.id
        WHERE md.event_id = ? AND md.matchday_number = 2
        ORDER BY m.id ASC
    ");
    $md2_stmt->execute([$info['event_id']]);
    $finals = $md2_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($finals) !== 2) {
        return;
    }

    fill_elimination_slot($db, (int) $finals[0]['id'], $winner_id);
    fill_elimination_slot($db, (int) $finals[1]['id'], $loser_id);
}

/**
 * Fallback: Halbfinal-Ergebnisse erneut in Finale / Platz-3 übernehmen.
 */
function repair_ko_bracket_4(PDO $db, int $event_id): array
{
    $event_stmt = $db->prepare("SELECT duration_type FROM events WHERE id = ?");
    $event_stmt->execute([$event_id]);
    $event = $event_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event || $event['duration_type'] !== 'kurz') {
        return ['success' => false, 'message' => 'Nur im Elimination-Modus verfügbar.'];
    }

    $teams_stmt = $db->prepare('SELECT COUNT(*) FROM teams WHERE event_id = ?');
    $teams_stmt->execute([$event_id]);
    if ((int) $teams_stmt->fetchColumn() !== 4) {
        return ['success' => false, 'message' => 'Reparatur nur für 4-Team-Elimination.'];
    }

    $md2_exists = $db->prepare('SELECT COUNT(*) FROM matchdays WHERE event_id = ? AND matchday_number = 2');
    $md2_exists->execute([$event_id]);
    if ((int) $md2_exists->fetchColumn() === 0) {
        $gen = generate_ko_bracket_4($db, $event_id, false);
        if (!$gen['success']) {
            return $gen;
        }
    }

    $sf_stmt = $db->prepare("
        SELECT m.id, m.score1, m.score2
        FROM matches m
        JOIN matchdays md ON m.matchday_id = md.id
        WHERE md.event_id = ? AND md.matchday_number = 1 AND m.status = 'finished'
        ORDER BY m.id ASC
    ");
    $sf_stmt->execute([$event_id]);
    $semifinals = $sf_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($semifinals as $sf) {
        advance_ko_after_result($db, (int) $sf['id'], (int) $sf['score1'], (int) $sf['score2']);
    }

    return [
        'success' => true,
        'message' => 'K.O.-Bracket aus abgeschlossenen Halbfinalen synchronisiert.',
    ];
}

function display_team_label(?string $name): string
{
    return ($name === null || $name === '') ? 'TBA' : $name;
}

function is_tba_slot($team_id, ?string $name): bool
{
    return $team_id === null || $team_id === '' || $name === null || $name === '';
}
