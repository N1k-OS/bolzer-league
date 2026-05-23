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

    try {
        $db->exec("ALTER TABLE teams ADD COLUMN group_name VARCHAR(50) NULL");
    } catch (PDOException $e) {}

    $teams_stmt = $db->prepare('SELECT id FROM teams WHERE event_id = ?');
    $teams_stmt->execute([$event_id]);
    $teams = $teams_stmt->fetchAll(PDO::FETCH_COLUMN);
    $num_teams = count($teams);

    if ($num_teams < 2) {
        return ['success' => false, 'message' => 'Zu wenige Teams. Mindestens 2 erforderlich.'];
    }

    $db->prepare('DELETE FROM matchdays WHERE event_id = ?')->execute([$event_id]);

    if ($mode === 'lang') {
        $db->prepare("UPDATE teams SET group_name = NULL WHERE event_id = ?")->execute([$event_id]);
        
        if ($num_teams % 2 !== 0) {
            $teams[] = 'GHOST';
            $num_teams++;
        }

        $total_rounds = $num_teams - 1;
        $matches_per_round = (int) ($num_teams / 2);
        $cycles = 2; // Hin- und Rückrunde
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
        return ['success' => true, 'message' => "Liga-Spielplan (Erweitert) angelegt."];
    } else {
        // Mode: standard (WM Format - Group Stage)
        shuffle($teams);
        
        $advancing = 2;
        while ($advancing * 2 < $num_teams) {
            $advancing *= 2;
        }
        $num_groups = max(1, $advancing / 2);
        
        $groups = [];
        $group_labels = range('A', 'Z');
        for ($i = 0; $i < $num_groups; $i++) {
            $groups[$group_labels[$i]] = [];
        }
        
        $current_group_idx = 0;
        $update_group_stmt = $db->prepare("UPDATE teams SET group_name = ? WHERE id = ?");
        foreach ($teams as $team_id) {
            $label = $group_labels[$current_group_idx];
            $groups[$label][] = $team_id;
            $update_group_stmt->execute(["Gruppe $label", $team_id]);
            
            $current_group_idx++;
            if ($current_group_idx >= $num_groups) {
                $current_group_idx = 0;
            }
        }
        
        $max_matchdays = 0;
        $group_schedules = [];
        
        foreach ($groups as $label => $group_teams) {
            $g_teams = $group_teams;
            $g_num = count($g_teams);
            if ($g_num % 2 !== 0) {
                $g_teams[] = 'GHOST';
                $g_num++;
            }
            
            $rounds = $g_num - 1;
            $matches_per_round = $g_num / 2;
            $schedule = [];
            for ($round = 0; $round < $rounds; $round++) {
                $round_matches = [];
                for ($match = 0; $match < $matches_per_round; $match++) {
                    $home = ($round + $match) % ($g_num - 1);
                    $away = ($g_num - 1 - $match + $round) % ($g_num - 1);
                    if ($match === 0) {
                        $away = $g_num - 1;
                    }
                    $home_team = $g_teams[$home];
                    $away_team = $g_teams[$away];
                    if ($home_team !== 'GHOST' && $away_team !== 'GHOST') {
                        $round_matches[] = [$home_team, $away_team];
                    }
                }
                $schedule[] = $round_matches;
            }
            $group_schedules[$label] = $schedule;
            if (count($schedule) > $max_matchdays) {
                $max_matchdays = count($schedule);
            }
        }
        
        for ($md = 0; $md < $max_matchdays; $md++) {
            $md_stmt = $db->prepare('INSERT INTO matchdays (event_id, matchday_number) VALUES (?, ?)');
            $md_stmt->execute([$event_id, $md + 1]);
            $matchday_id = $db->lastInsertId();
            
            foreach ($group_schedules as $label => $schedule) {
                if (isset($schedule[$md])) {
                    foreach ($schedule[$md] as $m) {
                        $db->prepare('INSERT INTO matches (matchday_id, team1_id, team2_id) VALUES (?, ?, ?)')
                            ->execute([$matchday_id, $m[0], $m[1]]);
                    }
                }
            }
        }
        
        return ['success' => true, 'message' => "WM-Spielplan (Gruppenphase) angelegt. Teams in $num_groups Gruppen aufgeteilt."];
    }
}

/** K.O.-Elimination: 4, 8 oder 16 Teams (Zweierpotenzen). */
function ko_supported_team_count(int $num_teams): bool
{
    return in_array($num_teams, [4, 8, 16], true);
}

function ko_total_rounds(int $num_teams): int
{
    return (int) log($num_teams, 2);
}

function ko_round_display_name(int $matches_in_round): string
{
    return match ($matches_in_round) {
        8 => 'Achtelfinale',
        4 => 'Viertelfinale',
        2 => 'Halbfinale',
        1 => 'Finale',
        default => 'Runde',
    };
}

function count_event_teams(PDO $db, int $event_id): int
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM teams WHERE event_id = ?');
    $stmt->execute([$event_id]);

    return (int) $stmt->fetchColumn();
}

/**
 * Kompletten K.O.-Baum anlegen: erste Runde mit Teams, Rest mit TBA-Plätzen.
 */
function generate_ko_bracket(PDO $db, int $event_id, bool $shuffle = true): array
{
    $teams_stmt = $db->prepare('SELECT id FROM teams WHERE event_id = ? ORDER BY id');
    $teams_stmt->execute([$event_id]);
    $teams = $teams_stmt->fetchAll(PDO::FETCH_COLUMN);
    $num_teams = count($teams);

    if (!ko_supported_team_count($num_teams)) {
        return [
            'success' => false,
            'message' => "Elimination (K.O.) benötigt genau 4, 8 oder 16 Teams, aktuell: $num_teams.",
        ];
    }

    $total_rounds = ko_total_rounds($num_teams);
    $db->prepare('DELETE FROM matchdays WHERE event_id = ?')->execute([$event_id]);

    if ($shuffle) {
        shuffle($teams);
    }

    $insert_md = $db->prepare('INSERT INTO matchdays (event_id, matchday_number) VALUES (?, ?)');
    $insert_match = $db->prepare(
        'INSERT INTO matches (matchday_id, team1_id, team2_id, status) VALUES (?, ?, ?, ?)'
    );

    for ($round = 1; $round <= $total_rounds; $round++) {
        $insert_md->execute([$event_id, $round]);
        $matchday_id = (int) $db->lastInsertId();

        if ($round === 1) {
            for ($i = 0; $i < $num_teams; $i += 2) {
                $insert_match->execute([$matchday_id, $teams[$i], $teams[$i + 1], 'upcoming']);
            }
            continue;
        }

        if ($round === $total_rounds) {
            $insert_match->execute([$matchday_id, null, null, 'upcoming']); // Finale
            continue;
        }

        $matches_in_round = (int) ($num_teams / pow(2, $round));
        for ($m = 0; $m < $matches_in_round; $m++) {
            $insert_match->execute([$matchday_id, null, null, 'upcoming']);
        }
    }

    $first_round = ko_round_display_name((int) ($num_teams / 2));

    return [
        'success' => true,
        'message' => "K.O.-Baum für $num_teams Teams angelegt ($first_round bis Finale/Platz 3).",
    ];
}

/** @deprecated Alias – nutze generate_ko_bracket() */
function generate_ko_bracket_4(PDO $db, int $event_id, bool $shuffle = true): array
{
    return generate_ko_bracket($db, $event_id, $shuffle);
}

function fill_elimination_team_slot(PDO $db, int $match_id, int $team_id, int $slot): void
{
    if ($slot !== 1 && $slot !== 2) {
        return;
    }

    $column = $slot === 1 ? 'team1_id' : 'team2_id';
    $db->prepare("UPDATE matches SET $column = ? WHERE id = ?")->execute([$team_id, $match_id]);
}

function get_ko_match_ids_for_round(PDO $db, int $event_id, int $matchday_number): array
{
    $stmt = $db->prepare("
        SELECT m.id
        FROM matches m
        JOIN matchdays md ON m.matchday_id = md.id
        WHERE md.event_id = ? AND md.matchday_number = ?
        ORDER BY m.id ASC
    ");
    $stmt->execute([$event_id, $matchday_number]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function get_ko_match_index_in_round(PDO $db, int $match_id): int
{
    $stmt = $db->prepare("
        SELECT m.id
        FROM matches m
        JOIN matchdays md ON m.matchday_id = md.id
        WHERE md.event_id = (
            SELECT md2.event_id FROM matches m2
            JOIN matchdays md2 ON m2.matchday_id = md2.id
            WHERE m2.id = ?
        )
        AND md.matchday_number = (
            SELECT md3.matchday_number FROM matches m3
            JOIN matchdays md3 ON m3.matchday_id = md3.id
            WHERE m3.id = ?
        )
        ORDER BY m.id ASC
    ");
    $stmt->execute([$match_id, $match_id]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $ids = array_map('intval', $ids);
    $index = array_search($match_id, $ids, true);

    return $index === false ? 0 : (int) $index;
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

    $event_id = (int) $info['event_id'];
    $team_count = count_event_teams($db, $event_id);

    if (!ko_supported_team_count($team_count)) {
        return;
    }

    $total_rounds = ko_total_rounds($team_count);
    $matchday = (int) $info['matchday_number'];

    if ($matchday >= $total_rounds) {
        return;
    }

    if ($score1 > $score2) {
        $winner_id = (int) $info['team1_id'];
        $loser_id = (int) $info['team2_id'];
    } elseif ($score2 > $score1) {
        $winner_id = (int) $info['team2_id'];
        $loser_id = (int) $info['team1_id'];
    } else {
        $t1 = (int) $info['team1_id'];
        $t2 = (int) $info['team2_id'];
        
        $stmt_stats = $db->prepare("
            SELECT 
                SUM(CASE WHEN m.team1_id = :id AND m.score1 > m.score2 THEN 3
                         WHEN m.team2_id = :id AND m.score2 > m.score1 THEN 3
                         WHEN m.score1 = m.score2 THEN 1 ELSE 0 END) as pts,
                SUM(CASE WHEN m.team1_id = :id THEN m.score1 - m.score2 ELSE m.score2 - m.score1 END) as diff
            FROM matches m
            JOIN matchdays md ON m.matchday_id = md.id
            WHERE md.event_id = :event_id AND (m.team1_id = :id OR m.team2_id = :id) AND m.status = 'finished'
        ");
        
        $stmt_stats->execute([':id' => $t1, ':event_id' => $event_id]);
        $s1 = $stmt_stats->fetch(PDO::FETCH_ASSOC);
        
        $stmt_stats->execute([':id' => $t2, ':event_id' => $event_id]);
        $s2 = $stmt_stats->fetch(PDO::FETCH_ASSOC);
        
        $w1 = false;
        if ((int)$s1['pts'] > (int)$s2['pts']) {
            $w1 = true;
        } elseif ((int)$s1['pts'] === (int)$s2['pts'] && (int)$s1['diff'] >= (int)$s2['diff']) {
            $w1 = true;
        }
        
        $winner_id = $w1 ? $t1 : $t2;
        $loser_id = $w1 ? $t2 : $t1;
    }
    $match_index = get_ko_match_index_in_round($db, $match_id);


    $next_round = get_ko_match_ids_for_round($db, $event_id, $matchday + 1);
    $next_match_index = (int) floor($match_index / 2);
    $next_slot = ($match_index % 2 === 0) ? 1 : 2;

    if (!isset($next_round[$next_match_index])) {
        return;
    }

    fill_elimination_team_slot($db, $next_round[$next_match_index], $winner_id, $next_slot);
}

function clear_ko_bracket_progress(PDO $db, int $event_id): void
{
    $db->prepare("
        UPDATE matches m
        JOIN matchdays md ON m.matchday_id = md.id
        SET m.team1_id = NULL, m.team2_id = NULL
        WHERE md.event_id = ? AND md.matchday_number > 1
    ")->execute([$event_id]);
}

/**
 * Fallback: alle abgeschlossenen K.O.-Runden erneut in Folge-Spiele übernehmen.
 */
function repair_ko_bracket(PDO $db, int $event_id): array
{
    $event_stmt = $db->prepare('SELECT duration_type FROM events WHERE id = ?');
    $event_stmt->execute([$event_id]);
    $event = $event_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event || $event['duration_type'] !== 'kurz') {
        return ['success' => false, 'message' => 'Nur im Elimination-Modus (K.O.) verfügbar.'];
    }

    $team_count = count_event_teams($db, $event_id);
    if (!ko_supported_team_count($team_count)) {
        return ['success' => false, 'message' => 'Reparatur nur für 4, 8 oder 16 Teams.'];
    }

    $md_exists = $db->prepare('SELECT COUNT(*) FROM matchdays WHERE event_id = ?');
    $md_exists->execute([$event_id]);
    if ((int) $md_exists->fetchColumn() === 0) {
        $gen = generate_ko_bracket($db, $event_id, false);
        if (!$gen['success']) {
            return $gen;
        }
    }

    $total_rounds = ko_total_rounds($team_count);
    clear_ko_bracket_progress($db, $event_id);

    $finished_stmt = $db->prepare("
        SELECT m.id, m.score1, m.score2
        FROM matches m
        JOIN matchdays md ON m.matchday_id = md.id
        WHERE md.event_id = ?
          AND md.matchday_number < ?
          AND m.status = 'finished'
          AND m.team1_id IS NOT NULL
          AND m.team2_id IS NOT NULL
        ORDER BY md.matchday_number ASC, m.id ASC
    ");
    $finished_stmt->execute([$event_id, $total_rounds]);
    $finished = $finished_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($finished as $match) {
        advance_ko_after_result(
            $db,
            (int) $match['id'],
            (int) $match['score1'],
            (int) $match['score2']
        );
    }

    return [
        'success' => true,
        'message' => 'K.O.-Bracket aus abgeschlossenen Spielen synchronisiert.',
    ];
}

/** @deprecated Alias – nutze repair_ko_bracket() */
function repair_ko_bracket_4(PDO $db, int $event_id): array
{
    return repair_ko_bracket($db, $event_id);
}

function display_team_label(?string $name): string
{
    return ($name === null || $name === '') ? 'TBA' : $name;
}

function is_tba_slot($team_id, ?string $name): bool
{
    return $team_id === null || $team_id === '' || $name === null || $name === '';
}

function generate_wm_ko_bracket(PDO $db, int $event_id): array
{
    $sql = "
        SELECT 
            t.id, t.name, t.group_name,
            COALESCE(SUM(CASE WHEN m.team1_id = t.id AND m.score1 > m.score2 THEN 3 WHEN m.team2_id = t.id AND m.score2 > m.score1 THEN 3 WHEN m.score1 = m.score2 THEN 1 ELSE 0 END), 0) AS pts,
            COALESCE(SUM(CASE WHEN m.team1_id = t.id THEN m.score1 - m.score2 ELSE m.score2 - m.score1 END), 0) AS diff,
            COALESCE(SUM(CASE WHEN m.team1_id = t.id THEN m.score1 ELSE m.score2 END), 0) AS goals
        FROM teams t
        LEFT JOIN matches m ON (m.team1_id = t.id OR m.team2_id = t.id) AND m.status = 'finished'
        LEFT JOIN matchdays md ON m.matchday_id = md.id AND md.event_id = :eid
        WHERE t.event_id = :eid2
        GROUP BY t.id, t.name, t.group_name
        ORDER BY t.group_name ASC, pts DESC, diff DESC, goals DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([':eid' => $event_id, ':eid2' => $event_id]);
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $groups = [];
    foreach ($teams as $t) {
        $g = $t['group_name'];
        if ($g) {
            $groups[$g][] = $t['id'];
        }
    }
    
    if (empty($groups)) {
        return ['success' => false, 'message' => 'Keine Gruppen gefunden. WM-Modus erfordert Gruppen.'];
    }
    
    $advancing_teams = [];
    foreach ($groups as $g_name => $g_teams) {
        $advancing_teams[$g_name] = [
            $g_teams[0] ?? null,
            $g_teams[1] ?? null
        ];
    }
    
    $num_groups = count($groups);
    if (!in_array($num_groups, [1, 2, 4, 8], true)) {
        return ['success' => false, 'message' => 'K.O.-Phase unterstützt aktuell nur 1, 2, 4 oder 8 Gruppen (bis zu 32 Teams).'];
    }
    
    $md_num_stmt = $db->prepare('SELECT COALESCE(MAX(matchday_number), 0) FROM matchdays WHERE event_id = ?');
    $md_num_stmt->execute([$event_id]);
    $current_md_num = (int)$md_num_stmt->fetchColumn();
    
    $ko_check = $db->prepare("SELECT COUNT(*) FROM matches m JOIN matchdays md ON m.matchday_id = md.id WHERE md.event_id = ? AND m.team1_id IS NULL");
    $ko_check->execute([$event_id]);
    if ($ko_check->fetchColumn() > 0) {
        return ['success' => false, 'message' => 'K.O.-Phase wurde bereits generiert.'];
    }
    
    $total_ko_rounds = (int) log($num_groups * 2, 2);
    
    $insert_md = $db->prepare('INSERT INTO matchdays (event_id, matchday_number) VALUES (?, ?)');
    $insert_match = $db->prepare('INSERT INTO matches (matchday_id, team1_id, team2_id, status) VALUES (?, ?, ?, ?)');
    
    $matchups = [];
    $group_keys = array_keys($advancing_teams);
    
    if ($num_groups === 1) {
        $matchups[] = [$advancing_teams[$group_keys[0]][0], $advancing_teams[$group_keys[0]][1]];
    } else {
        $half = $num_groups / 2;
        for ($i = 0; $i < $half; $i++) {
            $g1 = $i * 2;
            $g2 = $g1 + 1;
            $matchups[] = [$advancing_teams[$group_keys[$g1]][0], $advancing_teams[$group_keys[$g2]][1]];
        }
        for ($i = 0; $i < $half; $i++) {
            $g1 = $i * 2 + 1;
            $g2 = $i * 2;
            $matchups[] = [$advancing_teams[$group_keys[$g1]][0], $advancing_teams[$group_keys[$g2]][1]];
        }
    }
    
    for ($r = 1; $r <= $total_ko_rounds; $r++) {
        $insert_md->execute([$event_id, $current_md_num + $r]);
        $md_id = $db->lastInsertId();
        
        if ($r === 1) {
            foreach ($matchups as $m) {
                $insert_match->execute([$md_id, $m[0], $m[1], 'upcoming']);
            }
        } elseif ($r === $total_ko_rounds) {
            $insert_match->execute([$md_id, null, null, 'upcoming']);
        } else {
            $matches_in_round = count($matchups) / pow(2, $r - 1);
            for ($m = 0; $m < $matches_in_round; $m++) {
                $insert_match->execute([$md_id, null, null, 'upcoming']);
            }
        }
    }
    
    return ['success' => true, 'message' => 'K.O.-Phase erfolgreich generiert!'];
}
