<?php
// setup_dummy_data.php
session_start();
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    die("Zugriff verweigert. Nur Admins können Testdaten generieren.");
}
require_once 'includes/db.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Transaktion starten für schnelleres Einfügen
    $db->beginTransaction();

    // Einheitliches Passwort für alle Test-User
    $default_password = password_hash('test1234', PASSWORD_DEFAULT);
    $categories = ['a', 'b', 'c']; // Für etwas Abwechslung bei den Testdaten

    for ($i = 1; $i <= 16; $i++) {
        $alias = "TestSpieler_" . $i;
        $icon = "👤"; 
        
        // Zufällige Kategorie aus dem Array ziehen
        $random_cat = $categories[array_rand($categories)];

        // Prüfen, ob der User zufällig schon existiert (falls du das Skript 2x ausführst)
        $check = $db->prepare("SELECT id FROM users WHERE alias = :alias");
        $check->execute([':alias' => $alias]);
        
        if ($check->rowCount() == 0) {
            $stmt = $db->prepare("INSERT INTO users (alias, password_hash, icon, base_category) VALUES (:alias, :hash, :icon, :cat)");
            $stmt->execute([
                ':alias' => $alias,
                ':hash' => $default_password,
                ':icon' => $icon,
                ':cat' => $random_cat
            ]);
        }
    }

    $db->commit();
    echo "Erfolgreich! 16 Testspieler wurden angelegt (Login: TestSpieler_1 bis TestSpieler_16 | Passwort: test1234). Du kannst diese Datei jetzt wieder vom Server löschen.";

} catch (Exception $e) {
    // Bei einem Fehler wird alles zurückgerollt
    $db->rollBack();
    echo "Fehler beim Erstellen der User: " . $e->getMessage();
}
?>