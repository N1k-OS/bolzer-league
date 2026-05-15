<div class="page-header">
    <h2>Begegnungen</h2>
    <p>Spielplan und Ergebnisse.</p>
</div>

<?php
// Daten laden
$matches_json = file_get_contents('data/matches.json');
$data = json_decode($matches_json, true);
?>

<div class="accordion-container">

    <!-- AKKORDEON 1: ANSTEHENDE SPIELE -->
    <div class="accordion-item" style="border-left: 4px solid var(--primary-color);">
        <button class="accordion-header">
            <span class="team-name">⏳ Anstehende Spiele</span>
            <span class="accordion-icon">−</span> <!-- Standardmäßig offen -->
        </button>
        <!-- Style inline für max-height, damit es beim Laden direkt offen ist (JS kümmert sich um den Rest) -->
        <div class="accordion-content" style="max-height: 2000px;">
            <div class="matches-wrapper" style="padding: 10px;">
                
                <?php foreach ($data['upcoming'] as $day): ?>
                    <div class="matchday-group">
                        <div class="matchday-label">Spieltag <?php echo $day['matchday']; ?></div>
                        
                        <?php foreach ($day['matches'] as $match): ?>
                            <div class="match-card">
                                <div class="match-time"><?php echo $match['time']; ?> Uhr</div>
                                <div class="match-teams">
                                    <div class="m-team right-align">
                                        <span class="m-name"><?php echo $match['team1']; ?></span>
                                        <span class="m-icon"><?php echo $match['icon1']; ?></span>
                                    </div>
                                    <div class="m-vs">vs</div>
                                    <div class="m-team left-align">
                                        <span class="m-icon"><?php echo $match['icon2']; ?></span>
                                        <span class="m-name"><?php echo $match['team2']; ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                    </div>
                <?php endforeach; ?>

            </div>
        </div>
    </div>

    <!-- AKKORDEON 2: VERGANGENE SPIELE -->
    <div class="accordion-item" style="border-left: 4px solid gray;">
        <button class="accordion-header">
            <span class="team-name">✅ Vergangene Spiele</span>
            <span class="accordion-icon">+</span> <!-- Standardmäßig geschlossen -->
        </button>
        <div class="accordion-content">
            <div class="matches-wrapper" style="padding: 10px;">
                
                <?php foreach ($data['finished'] as $day): ?>
                    <div class="matchday-group">
                        <div class="matchday-label">Spieltag <?php echo $day['matchday']; ?></div>
                        
                        <?php foreach ($day['matches'] as $match): ?>
                            <div class="match-card finished">
                                <div class="match-teams">
                                    <div class="m-team right-align font-bold">
                                        <span class="m-name"><?php echo $match['team1']; ?></span>
                                        <span class="m-icon"><?php echo $match['icon1']; ?></span>
                                    </div>
                                    <div class="m-score">
                                        <?php echo $match['score1']; ?> : <?php echo $match['score2']; ?>
                                    </div>
                                    <div class="m-team left-align font-bold">
                                        <span class="m-icon"><?php echo $match['icon2']; ?></span>
                                        <span class="m-name"><?php echo $match['team2']; ?></span>
                                    </div>
                                </div>
                                <div class="match-mvp">
                                    <small>⭐ MVP: <?php echo $match['mvp']; ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                    </div>
                <?php endforeach; ?>

            </div>
        </div>
    </div>

</div>