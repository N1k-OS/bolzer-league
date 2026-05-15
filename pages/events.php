<div class="page-header">
    <h2>Bolzer-League Events</h2>
    <p>Aktuelle Turniere und Spieltage.</p>
</div>

<?php
require_once 'includes/db.php';
$database = new Database();
$db = $database->getConnection();

try {
    // 1. Das aktive Event finden
    $active_stmt = $db->query("SELECT * FROM events WHERE status = 'active' LIMIT 1");
    $active_event = $active_stmt->fetch();

    if ($active_event) {
        $mode_text = ($active_event['duration_type'] === 'kurz') ? 'Elimination (K.O.)' : 
                    (($active_event['duration_type'] === 'standard') ? 'Liga (Einfach)' : 'Liga (Erweitert)');
        ?>
        
        <h3 style="margin-bottom: 10px;">Jetzt Live</h3>
        <div class="card-container" style="border-left: 4px solid #2ed573;">
            <div style="padding: 20px;">
                <h3 style="margin-bottom: 5px; color: var(--primary-color);">🏆 <?php echo htmlspecialchars($active_event['name']); ?></h3>
                <p style="color: gray; font-size: 0.9rem; margin-bottom: 15px;">
                    Datum: <?php echo date('d.m.Y', strtotime($active_event['event_date'])); ?> | Modus: <?php echo $mode_text; ?>
                </p>
                <a href="index.php?page=matches" class="primary-btn" style="text-decoration: none; display: inline-block; text-align: center;">Zum Spielplan</a>
            </div>
        </div>

        <?php
    } else {
        echo "<div class='alert-box' style='padding: 20px; text-align: center; color: gray; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 20px;'>Aktuell läuft kein Event.</div>";
    }

    // 2. Anstehende (geplante) Events finden
    $upcoming_stmt = $db->query("SELECT * FROM events WHERE status = 'upcoming' ORDER BY event_date ASC");
    $upcoming_events = $upcoming_stmt->fetchAll();

    ?>
    <h3 style="margin-top: 30px; margin-bottom: 10px;">Geplante Events</h3>
    <?php
    if (empty($upcoming_events)) {
        echo "<p style='color: gray; font-size: 0.9rem;'>Keine zukünftigen Events geplant.</p>";
    } else {
        echo '<div class="card-container">';
        echo '<ul class="clean-list">';
        foreach ($upcoming_events as $event) {
            echo '<li class="list-item flex-align-center" style="justify-content: space-between;">';
            echo '  <div>';
            echo '      <div class="font-bold">' . htmlspecialchars($event['name']) . '</div>';
            echo '      <div style="font-size: 0.75rem; color: gray;">' . date('d.m.Y', strtotime($event['event_date'])) . '</div>';
            echo '  </div>';
            echo '  <span style="font-size: 1.2rem;">⏳</span>';
            echo '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

} catch (Exception $e) {
    echo "<p class='text-danger'>Fehler beim Laden der Events.</p>";
}
?>