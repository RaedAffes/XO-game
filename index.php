<!-- index.php -->
<?php
session_start();
require_once 'config.php';
// Configuration de la base de données
$host = 'sql301.infinityfree.com';
$dbname = 'if0_40529673_game';
$username = 'if0_40529673';
$password = 'B3nNa1wvkMNlqX'; // Remplacez par votre vrai mot de passe

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // Si la base n'existe pas, on continue sans DB
    $pdo = null;
}

// Initialiser les scores en session si nécessaire
if (!isset($_SESSION['scores'])) {
    $_SESSION['scores'] = [
        'x' => 0,
        'o' => 0,
        'draw' => 0
    ];
}

// Traiter les actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_score') {
        $winner = $_POST['winner'] ?? '';
        $mode = $_POST['mode'] ?? 'classic';
        
        if ($winner === 'X') {
            $_SESSION['scores']['x']++;
        } elseif ($winner === 'O') {
            $_SESSION['scores']['o']++;
        } else {
            $_SESSION['scores']['draw']++;
        }
        
        // Sauvegarder dans la base de données si disponible
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO games (winner, mode, game_date) VALUES (?, ?, NOW())");
                $stmt->execute([$winner, $mode]);
            } catch(PDOException $e) {
                // Erreur silencieuse
            }
        }
        
        echo json_encode(['success' => true, 'scores' => $_SESSION['scores']]);
        exit;
    }
    
    if ($action === 'reset_scores') {
        $_SESSION['scores'] = [
            'x' => 0,
            'o' => 0,
            'draw' => 0
        ];
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'get_stats') {
        $stats = ['total_games' => 0, 'recent_games' => []];
        
        if ($pdo) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM games");
                $stats['total_games'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                $stmt = $pdo->query("SELECT winner, mode, game_date FROM games ORDER BY game_date DESC LIMIT 10");
                $stats['recent_games'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch(PDOException $e) {
                // Erreur silencieuse
            }
        }
        
        echo json_encode($stats);
        exit;
    }
}

// Récupérer les scores actuels
$scores = $_SESSION['scores'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Jeux XO - PHP</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
        }

        h1 {
            color: #667eea;
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            font-size: 1.1em;
        }

        .main-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        .game-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .stats-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .menu {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .game-mode-btn {
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1em;
            cursor: pointer;
            transition: all 0.3s;
        }

        .game-mode-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }

        .game-container {
            display: none;
        }

        .game-container.active {
            display: block;
        }

        .game-info {
            text-align: center;
            margin-bottom: 20px;
        }

        .game-info h2 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.8em;
        }

        .status {
            font-size: 1.3em;
            color: #333;
            padding: 10px;
            background: #f0f0f0;
            border-radius: 8px;
            margin: 10px 0;
        }

        .board {
            display: grid;
            gap: 10px;
            margin: 20px auto;
            max-width: 450px;
        }

        .board.grid-3x3 {
            grid-template-columns: repeat(3, 1fr);
        }

        .board.grid-4x4 {
            grid-template-columns: repeat(4, 1fr);
        }

        .board.grid-5x5 {
            grid-template-columns: repeat(5, 1fr);
        }

        .cell {
            aspect-ratio: 1;
            background: #f8f9fa;
            border: 3px solid #e0e0e0;
            border-radius: 10px;
            font-size: 2.5em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cell:hover:not(:disabled) {
            background: #e8e9ea;
            transform: scale(1.05);
            border-color: #667eea;
        }

        .cell:disabled {
            cursor: not-allowed;
        }

        .cell.x {
            color: #667eea;
            background: #e8eaf6;
        }

        .cell.o {
            color: #764ba2;
            background: #f3e5f5;
        }

        .cell.winning {
            animation: pulse 0.5s ease-in-out;
            background: #ffd700;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .controls {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 600;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }

        .btn-secondary:hover {
            background: #d0d0d0;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .score-board {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
        }

        .score-board h3 {
            text-align: center;
            margin-bottom: 15px;
            font-size: 1.5em;
        }

        .score-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .score-item {
            text-align: center;
            background: rgba(255,255,255,0.2);
            padding: 15px;
            border-radius: 10px;
        }

        .score-label {
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .score-value {
            font-size: 2em;
            font-weight: bold;
        }

        .difficulty {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 15px 0;
        }

        .difficulty-btn {
            padding: 10px 20px;
            border: 2px solid #667eea;
            background: white;
            color: #667eea;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 600;
        }

        .difficulty-btn.active {
            background: #667eea;
            color: white;
        }

        .stats-content h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.3em;
        }

        .stat-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9em;
        }

        .stat-value {
            font-size: 1.5em;
            font-weight: bold;
            color: #667eea;
        }

        .recent-games {
            margin-top: 20px;
        }

        .game-history-item {
            padding: 10px;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            margin-bottom: 8px;
            border-radius: 5px;
        }

        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .menu {
                grid-template-columns: 1fr;
            }

            h1 {
                font-size: 1.8em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎮 Multi-Jeux XO</h1>
            <p class="subtitle">Application PHP avec sauvegarde des scores</p>
        </div>

        <div class="main-content">
            <div class="game-section">
                <div class="menu" id="menu">
                    <button class="game-mode-btn" onclick="startGame('classic')">
                        🎯 Classique 3x3<br>
                        <small>2 joueurs</small>
                    </button>
                    <button class="game-mode-btn" onclick="startGame('vs-ai')">
                        🤖 Contre IA<br>
                        <small>Mode solo</small>
                    </button>
                    <button class="game-mode-btn" onclick="startGame('4x4')">
                        📐 Grille 4x4<br>
                        <small>Défi avancé</small>
                    </button>
                    <button class="game-mode-btn" onclick="startGame('5x5')">
                        🎲 Grille 5x5<br>
                        <small>Expert</small>
                    </button>
                </div>

                <div class="game-container" id="game-container">
                    <div class="game-info">
                        <h2 id="game-title"></h2>
                        <div class="status" id="status">Tour de X</div>
                    </div>

                    <div class="difficulty" id="difficulty-container" style="display: none;">
                        <button class="difficulty-btn active" onclick="setDifficulty('easy')">😊 Facile</button>
                        <button class="difficulty-btn" onclick="setDifficulty('medium')">🤔 Moyen</button>
                        <button class="difficulty-btn" onclick="setDifficulty('hard')">🔥 Difficile</button>
                    </div>

                    <div class="board" id="board"></div>

                    <div class="controls">
                        <button class="btn btn-primary" onclick="resetGame()">🔄 Nouvelle Partie</button>
                        <button class="btn btn-secondary" onclick="backToMenu()">🏠 Menu</button>
                    </div>
                </div>
            </div>

            <div class="stats-section">
                <div class="score-board">
                    <h3>📊 Scores</h3>
                    <div class="score-grid">
                        <div class="score-item">
                            <div class="score-label">Joueur X</div>
                            <div class="score-value" id="score-x"><?php echo $scores['x']; ?></div>
                        </div>
                        <div class="score-item">
                            <div class="score-label">Égalités</div>
                            <div class="score-value" id="score-draw"><?php echo $scores['draw']; ?></div>
                        </div>
                        <div class="score-item">
                            <div class="score-label">Joueur O</div>
                            <div class="score-value" id="score-o"><?php echo $scores['o']; ?></div>
                        </div>
                    </div>
                </div>

                <div class="stats-content">
                    <h3>📈 Statistiques</h3>
                    <div class="stat-item">
                        <div class="stat-label">Total de parties</div>
                        <div class="stat-value" id="total-games">0</div>
                    </div>
                    
                    <button class="btn btn-danger" style="width: 100%; margin-top: 10px;" onclick="resetScores()">
                        🗑️ Réinitialiser
                    </button>

                    <div class="recent-games">
                        <h3>🕐 Historique</h3>
                        <div id="history-list"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentMode = '';
        let currentPlayer = 'X';
        let gameBoard = [];
        let gameActive = false;
        let gridSize = 3;
        let winCondition = 3;
        let aiDifficulty = 'medium';
        let winningCells = [];

        function startGame(mode) {
            currentMode = mode;
            document.getElementById('menu').style.display = 'none';
            document.getElementById('game-container').classList.add('active');
            
            const titles = {
                'classic': '🎯 Jeu Classique 3x3',
                'vs-ai': '🤖 Contre Intelligence Artificielle',
                '4x4': '📐 Grille 4x4 - Défi Avancé',
                '5x5': '🎲 Grille 5x5 - Mode Expert'
            };
            
            document.getElementById('game-title').textContent = titles[mode];
            
            const sizes = {
                'classic': [3, 3],
                'vs-ai': [3, 3],
                '4x4': [4, 4],
                '5x5': [5, 4]
            };
            
            [gridSize, winCondition] = sizes[mode];
            
            document.getElementById('difficulty-container').style.display = 
                mode === 'vs-ai' ? 'flex' : 'none';
            
            initBoard();
            resetGame();
        }

        function initBoard() {
            const board = document.getElementById('board');
            board.className = `board grid-${gridSize}x${gridSize}`;
            board.innerHTML = '';
            
            for(let i = 0; i < gridSize * gridSize; i++) {
                const cell = document.createElement('button');
                cell.className = 'cell';
                cell.onclick = () => handleCellClick(i);
                board.appendChild(cell);
            }
        }

        function resetGame() {
            gameBoard = Array(gridSize * gridSize).fill('');
            currentPlayer = 'X';
            gameActive = true;
            winningCells = [];
            
            const cells = document.querySelectorAll('.cell');
            cells.forEach(cell => {
                cell.textContent = '';
                cell.disabled = false;
                cell.className = 'cell';
            });
            
            updateStatus();
        }

        function handleCellClick(index) {
            if (!gameActive || gameBoard[index] !== '') return;
            
            makeMove(index, currentPlayer);
            
            if (gameActive && currentMode === 'vs-ai' && currentPlayer === 'O') {
                setTimeout(aiMove, 500);
            }
        }

        function makeMove(index, player) {
            gameBoard[index] = player;
            const cell = document.querySelectorAll('.cell')[index];
            cell.textContent = player;
            cell.className = `cell ${player.toLowerCase()}`;
            cell.disabled = true;
            
            const winResult = checkWinner(player);
            if (winResult) {
                gameActive = false;
                document.getElementById('status').textContent = `🎉 ${player} a gagné!`;
                highlightWinningCells(winResult);
                saveScore(player);
                disableAllCells();
                return;
            }
            
            if (gameBoard.every(cell => cell !== '')) {
                gameActive = false;
                document.getElementById('status').textContent = '🤝 Match nul!';
                saveScore('DRAW');
                return;
            }
            
            currentPlayer = currentPlayer === 'X' ? 'O' : 'X';
            updateStatus();
        }

        function checkWinner(player) {
            // Vérifier lignes
            for(let i = 0; i < gridSize; i++) {
                for(let j = 0; j <= gridSize - winCondition; j++) {
                    let cells = [];
                    let win = true;
                    for(let k = 0; k < winCondition; k++) {
                        let idx = i * gridSize + j + k;
                        cells.push(idx);
                        if(gameBoard[idx] !== player) {
                            win = false;
                            break;
                        }
                    }
                    if(win) return cells;
                }
            }
            
            // Vérifier colonnes
            for(let i = 0; i < gridSize; i++) {
                for(let j = 0; j <= gridSize - winCondition; j++) {
                    let cells = [];
                    let win = true;
                    for(let k = 0; k < winCondition; k++) {
                        let idx = (j + k) * gridSize + i;
                        cells.push(idx);
                        if(gameBoard[idx] !== player) {
                            win = false;
                            break;
                        }
                    }
                    if(win) return cells;
                }
            }
            
            // Vérifier diagonales
            for(let i = 0; i <= gridSize - winCondition; i++) {
                for(let j = 0; j <= gridSize - winCondition; j++) {
                    let cells1 = [], cells2 = [];
                    let win1 = true, win2 = true;
                    for(let k = 0; k < winCondition; k++) {
                        let idx1 = (i + k) * gridSize + (j + k);
                        let idx2 = (i + k) * gridSize + (j + winCondition - 1 - k);
                        cells1.push(idx1);
                        cells2.push(idx2);
                        if(gameBoard[idx1] !== player) win1 = false;
                        if(gameBoard[idx2] !== player) win2 = false;
                    }
                    if(win1) return cells1;
                    if(win2) return cells2;
                }
            }
            
            return null;
        }

        function highlightWinningCells(cells) {
            cells.forEach(idx => {
                document.querySelectorAll('.cell')[idx].classList.add('winning');
            });
        }

        function aiMove() {
            if (!gameActive) return;
            
            let move;
            if (aiDifficulty === 'easy') {
                move = getRandomMove();
            } else if (aiDifficulty === 'medium') {
                move = Math.random() < 0.5 ? getBestMove() : getRandomMove();
            } else {
                move = getBestMove();
            }
            
            if (move !== -1) {
                makeMove(move, 'O');
            }
        }

        function getRandomMove() {
            const available = gameBoard.map((cell, idx) => cell === '' ? idx : -1).filter(idx => idx !== -1);
            return available.length > 0 ? available[Math.floor(Math.random() * available.length)] : -1;
        }

        function getBestMove() {
            // Vérifier si l'IA peut gagner
            for(let i = 0; i < gameBoard.length; i++) {
                if(gameBoard[i] === '') {
                    gameBoard[i] = 'O';
                    if(checkWinner('O')) {
                        gameBoard[i] = '';
                        return i;
                    }
                    gameBoard[i] = '';
                }
            }
            
            // Bloquer le joueur
            for(let i = 0; i < gameBoard.length; i++) {
                if(gameBoard[i] === '') {
                    gameBoard[i] = 'X';
                    if(checkWinner('X')) {
                        gameBoard[i] = '';
                        return i;
                    }
                    gameBoard[i] = '';
                }
            }
            
            // Jouer au centre
            const center = Math.floor(gridSize * gridSize / 2);
            if(gameBoard[center] === '') return center;
            
            return getRandomMove();
        }

        function saveScore(winner) {
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=save_score&winner=${winner}&mode=${currentMode}`
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    updateScoreDisplay(data.scores);
                    loadStats();
                }
            });
        }

        function updateScoreDisplay(scores) {
            document.getElementById('score-x').textContent = scores.x;
            document.getElementById('score-o').textContent = scores.o;
            document.getElementById('score-draw').textContent = scores.draw;
        }

        function loadStats() {
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_stats'
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('total-games').textContent = data.total_games;
                
                const historyList = document.getElementById('history-list');
                historyList.innerHTML = '';
                
                data.recent_games.forEach(game => {
                    const div = document.createElement('div');
                    div.className = 'game-history-item';
                    const winnerText = game.winner === 'DRAW' ? '🤝 Égalité' : `🏆 ${game.winner} gagne`;
                    div.innerHTML = `
                        <strong>${winnerText}</strong><br>
                        <small>Mode: ${game.mode} - ${new Date(game.game_date).toLocaleString('fr-FR')}</small>
                    `;
                    historyList.appendChild(div);
                });
            });
        }

        function resetScores() {
            if(confirm('Voulez-vous vraiment réinitialiser tous les scores ?')) {
                fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=reset_scores'
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        location.reload();
                    }
                });
            }
        }

        function setDifficulty(level) {
            aiDifficulty = level;
            document.querySelectorAll('.difficulty-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
        }

        function updateStatus() {
            const emoji = currentPlayer === 'X' ? '❌' : '⭕';
            document.getElementById('status').textContent = `${emoji} Tour de ${currentPlayer}`;
        }

        function disableAllCells() {
            document.querySelectorAll('.cell').forEach(cell => {
                cell.disabled = true;
            });
        }

        function backToMenu() {
            document.getElementById('game-container').classList.remove('active');
            document.getElementById('menu').style.display = 'grid';
        }

        // Charger les stats au démarrage
        loadStats();
    </script>
</body>
</html>