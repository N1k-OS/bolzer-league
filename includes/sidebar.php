<nav class="sidebar">
        <div class="brand-logo">
        <a href="index.php" style="color: white; text-decoration: none; display: block;">
            ⚽<br><small>BL</small>
        </a>
    </div>
    
    <div class="sidebar-icons">
        <?php foreach ($tabs as $tab): 
            // Überspringen, wenn Admin-Tab, aber User kein Admin
            if (isset($tab['admin_only']) && $tab['admin_only'] && (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin'])) {
                continue;
            }
        ?>
            <?php if (!isset($tab['bottom']) || !$tab['bottom']): ?>
                <a href="index.php?page=<?php echo $tab['id']; ?>" 
                   class="nav-item <?php echo ($page == $tab['id']) ? 'active' : ''; ?>" 
                   title="<?php echo $tab['title']; ?>">
                    <?php echo $tab['icon']; ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Unterer Bereich für Dark Mode und Settings/Admin -->
    <div class="sidebar-bottom">
        <button type="button" id="theme-toggle" class="nav-item icon-btn" title="Tag/Nacht Modus">🌗</button>
        
        <?php foreach ($tabs as $tab): 
            // Überspringen, wenn Admin-Tab, aber User kein Admin
            if (isset($tab['admin_only']) && $tab['admin_only'] && (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin'])) {
                continue;
            }
        ?>
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