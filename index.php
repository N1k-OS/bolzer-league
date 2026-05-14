<?php
// 1. Welche Seite wurde angeklickt? (Standard ist 'events')
$page = isset($_GET['page']) ? $_GET['page'] : 'events';

// 2. Sicherheits-Check: Wir prüfen in der JSON, ob es diesen Tab überhaupt gibt
$tabs_json = file_get_contents('config/tabs.json');
$tabs = json_decode($tabs_json, true);

$allowed_pages = array_column($tabs, 'id'); // Holt alle IDs wie 'events', 'teams'
if (!in_array($page, $allowed_pages)) {
    $page = 'events'; // Fallback, falls jemand eine falsche URL eingibt
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Bolzer-League</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="app-layout" style="display: flex; height: 100vh; overflow: hidden; width: 100%;">
        
        <!-- Puzzleteil 1: Die linke Navigationsleiste (lädt dynamisch aus der JSON) -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Rechter Hauptbereich (Content) -->
        <main class="content-area" style="flex-grow: 1; overflow-y: auto; background-color: var(--bg-color);">
            
            <div class="page-content">
                <!-- Puzzleteil 2: Hier lädt PHP genau die EINE Datei rein, die gebraucht wird -->
                <?php include "pages/{$page}.php"; ?>
            </div>

        </main>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>