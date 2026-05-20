<?php
// api/settings_update.php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Zugriff verweigert']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];
$formType = $_POST['form_type'] ?? '';

try {
    if ($formType === 'profile') {
        $new_alias = trim($_POST['alias']);
        if (empty($new_alias)) {
            echo json_encode(['success' => false, 'message' => 'Name darf nicht leer sein.']);
            exit;
        }
        
        // Prüfen, ob der Name schon von wem anders genutzt wird
        $check = $db->prepare("SELECT id FROM users WHERE alias = ? AND id != ?");
        $check->execute([$new_alias, $user_id]);
        if ($check->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Dieser Name ist bereits vergeben.']);
            exit;
        }

        $new_icon = mb_substr($new_alias, 0, 1);
        
        // Ensure icon column exists
        try {
            $db->exec("ALTER TABLE users ADD COLUMN icon VARCHAR(255) NULL");
        } catch (Exception $e) {}
        
        $stmt = $db->prepare("UPDATE users SET alias = ?, icon = ? WHERE id = ?");
        $stmt->execute([$new_alias, $new_icon, $user_id]);
        
        // Session aktualisieren
        $_SESSION['alias'] = $new_alias;
        
        echo json_encode(['success' => true, 'message' => 'Profil aktualisiert!']);
    } 
    
    elseif ($formType === 'email') {
        $email = trim($_POST['email']);
        $notifications = (isset($_POST['notifications']) && $_POST['notifications'] == 'true') ? 1 : 0;
        
        // Ensure email and email_notifications columns exist
        try {
            $db->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL");
        } catch (Exception $e) {}
        try {
            $db->exec("ALTER TABLE users ADD COLUMN email_notifications TINYINT(1) NOT NULL DEFAULT 1");
        } catch (Exception $e) {}
        
        $stmt = $db->prepare("UPDATE users SET email = ?, email_notifications = ? WHERE id = ?");
        $stmt->execute([empty($email) ? NULL : $email, $notifications, $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'Präferenzen gespeichert!']);
    }
    
    elseif ($formType === 'password') {
        $old_pwd = $_POST['pwd_old'];
        $new_pwd = $_POST['pwd_new'];
        
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($old_pwd, $user['password_hash'])) {
            $new_hash = password_hash($new_pwd, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$new_hash, $user_id]);
            echo json_encode(['success' => true, 'message' => 'Passwort erfolgreich geändert!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Altes Passwort ist falsch.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Unbekannter Formulartyp.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Datenbank-Fehler.']);
}
?>