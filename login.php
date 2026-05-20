<?php
session_start();
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
</head>
<body class="auth-page">

    <div class="auth-container">
        <div class="auth-logo">⚽</div>
        <h2 class="auth-title" id="form-title">Login</h2>

        <div id="error-box" class="error-message"></div>

        <form id="auth-form" onsubmit="handleAuth(event)">
            <div class="form-group">
                <label for="alias">Alias</label>
                <input type="text" id="alias" class="form-input" required placeholder="z.B. Nikos">
            </div>

            <div class="form-group u-hidden" id="category-group">
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

            <button type="submit" class="primary-btn u-mt-10" id="submit-btn">Einloggen</button>
        </form>

        <div class="switch-mode">
            <span id="switch-text">Noch keinen Account?</span>
            <a onclick="toggleMode()" id="switch-link">Registrieren</a>
        </div>
    </div>

    <script>
        let isLoginMode = true;

        function toggleMode() {
            isLoginMode = !isLoginMode;

            const title = document.getElementById('form-title');
            const catGroup = document.getElementById('category-group');
            const submitBtn = document.getElementById('submit-btn');
            const switchText = document.getElementById('switch-text');
            const switchLink = document.getElementById('switch-link');
            const errorBox = document.getElementById('error-box');

            errorBox.classList.remove('is-visible');

            if (isLoginMode) {
                title.textContent = "Login";
                catGroup.classList.add('u-hidden');
                document.getElementById('category').removeAttribute('required');
                submitBtn.textContent = "Einloggen";
                switchText.textContent = "Noch keinen Account?";
                switchLink.textContent = "Registrieren";
            } else {
                title.textContent = "Registrieren";
                catGroup.classList.remove('u-hidden');
                document.getElementById('category').setAttribute('required', 'required');
                submitBtn.textContent = "Account erstellen";
                switchText.textContent = "Bereits registriert?";
                switchLink.textContent = "Zum Login";
            }
        }

        async function handleAuth(event) {
            event.preventDefault();

            const alias = document.getElementById('alias').value;
            const password = document.getElementById('password').value;
            const btn = document.getElementById('submit-btn');
            const errorBox = document.getElementById('error-box');

            btn.disabled = true;
            btn.textContent = "Bitte warten...";

            const formData = new FormData();
            formData.append('action', isLoginMode ? 'login' : 'register');
            formData.append('alias', alias);
            formData.append('password', password);

            if (!isLoginMode) {
                formData.append('category', document.getElementById('category').value);
            }

            try {
                const response = await fetch('api/auth.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    window.location.href = "index.php";
                } else {
                    errorBox.textContent = result.message;
                    errorBox.classList.add('is-visible');
                    btn.disabled = false;
                    btn.textContent = isLoginMode ? "Einloggen" : "Account erstellen";
                }
            } catch (error) {
                errorBox.textContent = "Server-Fehler. Bist du online?";
                errorBox.classList.add('is-visible');
                btn.disabled = false;
                btn.textContent = isLoginMode ? "Einloggen" : "Account erstellen";
            }
        }
    </script>
</body>
</html>
