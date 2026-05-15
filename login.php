<?php
session_start();
// Wenn man schon eingeloggt ist, direkt in die App leiten
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Login - Bolzer-League</title>
    <meta name="theme-color" content="#007bff">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Spezifisches CSS nur für die Login-Seite */
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100dvh;
            background-color: var(--sidebar-bg); /* Blau im Light Mode, Dunkel im Dark Mode */
            padding: 20px;
        }
        .auth-container {
            background-color: var(--card-bg);
            width: 100%;
            max-width: 400px;
            padding: 30px 20px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            text-align: center;
        }
        .auth-logo {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        .auth-title {
            margin-bottom: 25px;
            color: var(--text-color);
        }
        .form-group {
            text-align: left;
            margin-bottom: 15px;
        }
        .form-select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background-color: var(--bg-color);
            color: var(--text-color);
            font-size: 1rem;
        }
        .error-message {
            color: #ff4757;
            font-size: 0.85rem;
            margin-bottom: 15px;
            display: none;
        }
        .switch-mode {
            margin-top: 20px;
            font-size: 0.9rem;
            color: gray;
        }
        .switch-mode a {
            color: var(--primary-color);
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
        }
    </style>
</head>
<body>

    <div class="auth-container">
        <div class="auth-logo">⚽</div>
        <h2 class="auth-title" id="form-title">Login</h2>
        
        <div id="error-box" class="error-message">Fehlermeldung kommt hier hin.</div>

        <!-- Das Formular (wird per JS gehandhabt) -->
        <form id="auth-form" onsubmit="handleAuth(event)">
            
            <div class="form-group">
                <label for="alias">Name (Alias)</label>
                <input type="text" id="alias" class="form-input" required placeholder="z.B. Nikos">
            </div>

            <!-- Dieses Feld ist standardmäßig unsichtbar und wird nur bei Registrierung gezeigt -->
            <div class="form-group" id="category-group" style="display: none;">
                <label for="category">Start-Kategorie</label>
                <select id="category" class="form-select">
                    <option value="a">Kategorie A (Stark)</option>
                    <option value="b">Kategorie B (Mittel)</option>
                    <option value="c" selected>Kategorie C (Basis)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="password">Passwort</label>
                <input type="password" id="password" class="form-input" required placeholder="••••••••">
            </div>

            <button type="submit" class="primary-btn" id="submit-btn" style="margin-top: 10px;">Einloggen</button>
        </form>

        <div class="switch-mode">
            <span id="switch-text">Noch keinen Account?</span> 
            <a onclick="toggleMode()" id="switch-link">Registrieren</a>
        </div>
    </div>

    <script>
        // JS direkt hier drin, um es kompakt zu halten
        let isLoginMode = true;

        function toggleMode() {
            isLoginMode = !isLoginMode;
            
            const title = document.getElementById('form-title');
            const catGroup = document.getElementById('category-group');
            const submitBtn = document.getElementById('submit-btn');
            const switchText = document.getElementById('switch-text');
            const switchLink = document.getElementById('switch-link');
            const errorBox = document.getElementById('error-box');
            
            errorBox.style.display = 'none'; // Fehler verstecken beim Umschalten

            if (isLoginMode) {
                title.textContent = "Login";
                catGroup.style.display = 'none';
                document.getElementById('category').removeAttribute('required');
                submitBtn.textContent = "Einloggen";
                switchText.textContent = "Noch keinen Account?";
                switchLink.textContent = "Registrieren";
            } else {
                title.textContent = "Registrieren";
                catGroup.style.display = 'block';
                document.getElementById('category').setAttribute('required', 'required');
                submitBtn.textContent = "Account erstellen";
                switchText.textContent = "Bereits registriert?";
                switchLink.textContent = "Zum Login";
            }
        }

        async function handleAuth(event) {
            event.preventDefault(); // Verhindert Neuladen der Seite
            
            const alias = document.getElementById('alias').value;
            const password = document.getElementById('password').value;
            const btn = document.getElementById('submit-btn');
            const errorBox = document.getElementById('error-box');
            
            btn.disabled = true;
            btn.textContent = "Bitte warten...";
            
            // Formulardaten für PHP aufbauen
            const formData = new FormData();
            formData.append('action', isLoginMode ? 'login' : 'register');
            formData.append('alias', alias);
            formData.append('password', password);
            
            if (!isLoginMode) {
                formData.append('category', document.getElementById('category').value);
            }

            try {
                // Den Endpoint (auth.php) aufrufen, den wir vorhin gebaut haben
                const response = await fetch('api/auth.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Erfolgreich! Wir leiten zur App weiter.
                    window.location.href = "index.php";
                } else {
                    // Fehler (z.B. falsches Passwort oder Name vergeben)
                    errorBox.textContent = result.message;
                    errorBox.style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = isLoginMode ? "Einloggen" : "Account erstellen";
                }
            } catch (error) {
                errorBox.textContent = "Server-Fehler. Bist du online?";
                errorBox.style.display = 'block';
                btn.disabled = false;
                btn.textContent = isLoginMode ? "Einloggen" : "Account erstellen";
            }
        }
    </script>
</body>
</html>