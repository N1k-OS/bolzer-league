<?php
// WICHTIG: Session starten muss ganz oben passieren!
session_start();

// Wenn der User nicht eingeloggt ist, zur Login-Seite weiterleiten
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Hier beginnt dein alter Code...
$page = isset($_GET['page']) ? $_GET['page'] : 'events';
// ...
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Bolzer-League</title>
    
    <meta name="theme-color" content="#007bff" id="meta-theme-color">
    
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime('assets/css/style.css'); ?>">
</head>
<body>

    <div class="app-layout">
        
        <?php include 'includes/sidebar.php'; ?>

        <main class="content-area">
            <div class="page-content">
                <?php include "pages/{$page}.php"; ?>
            </div>
        </main>
    </div>

    <script src="assets/js/app.js?v=<?php echo filemtime('assets/js/app.js'); ?>"></script>
</body>
</html>