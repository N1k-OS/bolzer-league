<?php
// api/get_teams.php

// 1. Wir sagen dem Browser/Frontend: Hier kommt gleich JSON (kein HTML!)
header("Content-Type: application/json; charset=UTF-8");

// 2. Datenbank-Klasse einbinden
include_once '../includes/db.php';

// (Optional) Hier würden wir uns später mit der DB verbinden:
// $database = new Database();
// $db = $database->getConnection();

// --- PLATZHALTER (Mock-Daten) ---
// Bis wir die echten SQL-Tabellen angelegt haben, geben wir einfach 
// das Dummy-Array zurück, das wir vorher in der data/teams.json hatten.
$teams = [
    [
        "id" => 1,
        "name" => "Team 1",
        "icon" => "🛡️",
        "players" => [
            ["name" => "Aris", "category" => "a", "cat_label" => "Kat A"],
            ["name" => "Mikael", "category" => "b", "cat_label" => "Kat B"],
            ["name" => "Efi", "category" => "c", "cat_label" => "Kat C"],
            ["name" => "Nikos", "category" => "b", "cat_label" => "Kat B"]
        ]
    ],
    [
        "id" => 2,
        "name" => "Team 2",
        "icon" => "🦁",
        "players" => [
            ["name" => "Pavel", "category" => "a", "cat_label" => "Kat A"],
            ["name" => "Cristiano", "category" => "b", "cat_label" => "Kat B"]
        ]
    ]
];

// 3. Das PHP-Array in einen JSON-String umwandeln und ausgeben
echo json_encode($teams);
?>