<div class="page-header">
    <h2>Einstellungen</h2>
    <p>Profil und Präferenzen verwalten.</p>
</div>

<div class="settings-container">

    <!-- BLOCK 1: Profil-Infos -->
    <div class="card-container settings-card">
        <h3 class="settings-title">👤 Öffentliches Profil</h3>
        
        <form onsubmit="saveSettings(event, 'profile')">
            <div class="form-group flex-align-center" style="margin-bottom: 20px;">
                <div class="avatar-circle" style="width: 60px; height: 60px; font-size: 1.8rem; margin-right: 20px;">
                    M <!-- Hier kommt später der echte Initiale hin -->
                </div>
                <div>
                    <label style="display:block; font-size: 0.85rem; color: gray; margin-bottom: 5px;">Profilbild wird automatisch generiert</label>
                    <button type="button" class="primary-btn" style="padding: 5px 15px; font-size: 0.85rem; width: auto; background-color: var(--sidebar-active);">Farbe ändern</button>
                </div>
            </div>

            <div class="form-group">
                <label for="alias-input">Alias / Name</label>
                <input type="text" id="alias-input" class="form-input" value="Mikael" required>
            </div>
            
            <button type="submit" class="primary-btn">Profil speichern</button>
        </form>
    </div>

    <!-- BLOCK 2: E-Mail & Benachrichtigungen -->
    <div class="card-container settings-card">
        <h3 class="settings-title">✉️ Benachrichtigungen</h3>
        
        <form onsubmit="saveSettings(event, 'email')">
            <div class="form-group">
                <label for="email-input">E-Mail Adresse</label>
                <input type="email" id="email-input" class="form-input" placeholder="deine@email.de">
            </div>

            <div class="form-group flex-align-center" style="justify-content: space-between; margin-top: 15px;">
                <label for="reminder-toggle" style="margin-bottom: 0;">Am Event-Tag per E-Mail erinnern</label>
                <!-- Custom Toggle Switch -->
                <label class="toggle-switch">
                    <input type="checkbox" id="reminder-toggle" checked>
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <button type="submit" class="primary-btn" style="margin-top: 15px;">Präferenzen speichern</button>
        </form>
    </div>

    <!-- BLOCK 3: Sicherheit -->
    <div class="card-container settings-card">
        <h3 class="settings-title">🔒 Sicherheit</h3>
        
        <form onsubmit="saveSettings(event, 'password')">
            <div class="form-group">
                <label for="pwd-old">Aktuelles Passwort</label>
                <input type="password" id="pwd-old" class="form-input" required>
            </div>
            <div class="form-group">
                <label for="pwd-new">Neues Passwort</label>
                <input type="password" id="pwd-new" class="form-input" required>
            </div>
            
            <button type="submit" class="primary-btn danger-btn">Passwort ändern</button>
        </form>
    </div>
    
    <!-- Logout Button ganz unten -->
    <div style="text-align: center; margin-top: 30px; margin-bottom: 30px;">
        <button class="primary-btn" style="background-color: transparent; color: #ff4757; border: 1px solid #ff4757;" onclick="logout()">Abmelden</button>
    </div>

</div>