<div class="page-header">
    <h2>Turnierbaum</h2>
    <p>Der Weg zum Titel.</p>
</div>

<div class="bracket-scroll-container">
    <div class="bracket-wrapper">
        
        <?php
        $bracket_json = file_get_contents('data/bracket.json');
        $rounds = json_decode($bracket_json, true);
        
        foreach ($rounds as $round): ?>
            <div class="bracket-round">
                <div class="round-title"><?php echo htmlspecialchars($round['round_name']); ?></div>
                
                <div class="round-matches">
                    <?php foreach ($round['matches'] as $match): ?>
                        <div class="bracket-match <?php echo $match['status']; ?>">
                            
                            <!-- Team 1 -->
                            <div class="b-team <?php echo ($match['score1'] !== null && $match['score1'] > $match['score2']) ? 'winner' : ''; ?>">
                                <span class="b-name"><?php echo htmlspecialchars($match['team1']); ?></span>
                                <span class="b-score"><?php echo ($match['score1'] !== null) ? $match['score1'] : '-'; ?></span>
                            </div>
                            
                            <!-- Team 2 -->
                            <div class="b-team <?php echo ($match['score2'] !== null && $match['score2'] > $match['score1']) ? 'winner' : ''; ?>">
                                <span class="b-name"><?php echo htmlspecialchars($match['team2']); ?></span>
                                <span class="b-score"><?php echo ($match['score2'] !== null) ? $match['score2'] : '-'; ?></span>
                            </div>
                            
                        </div>
                        
                        <!-- Verbindungslinie zum nächsten Spiel (außer in der letzten Runde) -->
                        <div class="bracket-connector"></div>
                    <?php endforeach; ?>
                </div>
                
            </div>
        <?php endforeach; ?>

    </div>
</div>