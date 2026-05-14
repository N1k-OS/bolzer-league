<?php
// JSON-Datei laden und in ein PHP-Array umwandeln
$tabs = json_decode($tabs_json, true);
?>
<nav class="sidebar">
    <div class="brand-logo">
        ⚽<br><small>BL</small>
    </div>
    
    <div class="sidebar-icons">
        <?php foreach ($tabs as $tab): ?>
            <?php if (!isset($tab['bottom']) || !$tab['bottom']): ?>
                <a href="index.php?page=<?php echo $tab['id']; ?>" 
                   class="nav-item <?php echo ($page == $tab['id']) ? 'active' : ''; ?>" 
                   title="<?php echo $tab['title']; ?>">
                    <?php echo $tab['icon']; ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Unterer Bereich für Dark Mode und Settings -->
    <div class="sidebar-bottom">
        <button id="theme-toggle" class="nav-item icon-btn" title="Tag/Nacht Modus">🌗</button>
        
        <?php foreach ($tabs as $tab): ?>
            <?php if (isset($tab['bottom']) && $tab['bottom']): ?>
                <a href="index.php?page=<?php echo $tab['id']; ?>" 
                   class="nav-item settings-item <?php echo ($page == $tab['id']) ? 'active' : ''; ?>" 
                   title="<?php echo $tab['title']; ?>">
                    <?php echo $tab['icon']; ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</nav>