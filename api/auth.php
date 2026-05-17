<?php
// api/auth.php
session_start();
require_once '../includes/db.php';

// Wir sagen dem Browser, dass JSON zurückkommt
header('Content-Type: application/json');

// Nur POST-Requests erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Ungültige Anfrage']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$action = $_POST['action'] ?? '';

// ---------------------------------------------------------
// REGISTRIERUNG
// ---------------------------------------------------------
if ($action === 'register') {
    $alias = trim($_POST['alias']);
    $password = $_POST['password'];
    $category = $_POST['category']; // 'a', 'b' oder 'c'

    if (empty($alias) || empty($password) || empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Bitte alle Felder ausfüllen']);
        exit;
    }

    try {
        // Prüfen, ob der Alias schon existiert
        $check = $db->prepare("SELECT id FROM users WHERE alias = :alias");
        $check->execute([':alias' => $alias]);
        if ($check->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Dieser Name ist bereits vergeben']);
            exit;
        }

        // Passwort verschlüsseln (Sicherheit!)
        $hash = password_hash($password, PASSWORD_DEFAULT);
        // Initiale aus dem Namen generieren
        $icon = mb_substr($alias, 0, 1);

        $stmt = $db->prepare("INSERT INTO users (alias, password_hash, icon, base_category) VALUES (:alias, :hash, :icon, :cat)");
        $stmt->execute([
            ':alias' => $alias,
            ':hash' => $hash,
            ':icon' => $icon,
            ':cat' => $category
        ]);

        // Direkt einloggen nach Registrierung
        $_SESSION['user_id'] = $db->lastInsertId();
        $_SESSION['alias'] = $alias;
        $_SESSION['is_admin'] = false; // Standard-User

        echo json_encode(['success' => true, 'message' => 'Erfolgreich registriert']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Datenbankfehler bei der Registrierung']);
    }
}

// ---------------------------------------------------------
// LOGIN
// ---------------------------------------------------------
elseif ($action === 'login') {
    $alias = trim($_POST['alias']);
    $password = $_POST['password'];

    try {
        $stmt = $db->prepare("SELECT id, alias, password_hash, is_admin FROM users WHERE alias = :alias");
        $stmt->execute([':alias' => $alias]);
        $user = $stmt->fetch();

        // Passwort verifizieren
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['alias'] = $user['alias'];
            $_SESSION['is_admin'] = (bool)$user['is_admin'];

            echo json_encode(['success' => true, 'message' => 'Login erfolgreich']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Falscher Name oder Passwort']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Datenbankfehler beim Login']);
    }
}

// ---------------------------------------------------------
// LOGOUT
// ---------------------------------------------------------
elseif ($action === 'logout') {
    session_unset();
    session_destroy();
    echo json_encode(['success' => true]);
}
?>