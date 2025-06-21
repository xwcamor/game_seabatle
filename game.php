<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

define('BOARD_SIZE', 10);
$ships_config = [
    ['name' => 'Portaaviones', 'size' => 5, 'count' => 1],
    ['name' => 'Acorazado', 'size' => 4, 'count' => 1],
    ['name' => 'Crucero', 'size' => 3, 'count' => 1],
    ['name' => 'Submarino', 'size' => 3, 'count' => 1],
    ['name' => 'Destructor', 'size' => 2, 'count' => 1]
];

$stmt = $pdo->prepare("SELECT * FROM games WHERE user_id = ? AND (status = 'setup' OR status = 'playing') ORDER BY updated_at DESC LIMIT 1");
$stmt->execute([$user_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

$player_board_data = [];
$opponent_board_data = [];
$player_shots_data = [];
$opponent_shots_data = []; // Disparos de la IA en el tablero del jugador

if ($game) {
    $player_board_data = $game['player_board'] ? json_decode($game['player_board'], true) : array_fill(0, BOARD_SIZE, array_fill(0, BOARD_SIZE, 0));
    $opponent_board_data = $game['opponent_board'] ? json_decode($game['opponent_board'], true) : array_fill(0, BOARD_SIZE, array_fill(0, BOARD_SIZE, 0));
    $player_shots_data = $game['player_shots'] ? json_decode($game['player_shots'], true) : array_fill(0, BOARD_SIZE, array_fill(0, BOARD_SIZE, null));
    $opponent_shots_data = $game['opponent_shots'] ? json_decode($game['opponent_shots'], true) : array_fill(0, BOARD_SIZE, array_fill(0, BOARD_SIZE, null));
    $game_status = $game['status'];
    $current_turn = $game['current_turn'];
    $game_id = $game['id'];
} else {
    $empty_board_json = json_encode(array_fill(0, BOARD_SIZE, array_fill(0, BOARD_SIZE, 0)));
    $empty_shots_json = json_encode(array_fill(0, BOARD_SIZE, array_fill(0, BOARD_SIZE, null)));
    $stmt = $pdo->prepare("INSERT INTO games (user_id, status, player_board, opponent_board, player_shots, opponent_shots, current_turn) VALUES (?, 'setup', ?, ?, ?, ?, 'player')");
    $stmt->execute([$user_id, $empty_board_json, $empty_board_json, $empty_shots_json, $empty_shots_json]);
    $game_id = $pdo->lastInsertId();
    $game_status = 'setup';
    $current_turn = 'player';
    $player_board_data = json_decode($empty_board_json, true);
    $opponent_board_data = json_decode($empty_board_json, true); // La IA se generará al confirmar
    $player_shots_data = json_decode($empty_shots_json, true);
    $opponent_shots_data = json_decode($empty_shots_json, true);
}

function render_board($board_id, $board_data, $is_opponent_board = false, $shots_data = [], $own_shots_on_player = []) {
    echo "<div id='$board_id' class='grid'>";
    for ($row = 0; $row < BOARD_SIZE; $row++) {
        for ($col = 0; $col < BOARD_SIZE; $col++) {
            $cell_id = "{$board_id}_cell_{$row}_{$col}";
            $cell_class = 'cell';

            if ($is_opponent_board) { // Tablero del oponente (donde el jugador dispara)
                if (isset($shots_data[$row][$col])) { // $shots_data son los disparos del jugador
                    if ($shots_data[$row][$col] === 'H') $cell_class .= ' hit';
                    elseif ($shots_data[$row][$col] === 'M') $cell_class .= ' miss';
                }
            } else { // Tablero del jugador
                if (isset($board_data[$row][$col]) && $board_data[$row][$col] === 1) { // Barco del jugador
                    $cell_class .= ' ship';
                }
                // Marcar impactos de la IA en el tablero del jugador
                if (isset($own_shots_on_player[$row][$col])) {
                    if ($own_shots_on_player[$row][$col] === 'H' && $board_data[$row][$col] === 1) { // Si la IA acertó un barco
                        $cell_class .= ' hit'; // Sobrescribe 'ship' visualmente con 'hit'
                    } elseif ($own_shots_on_player[$row][$col] === 'M' && $board_data[$row][$col] === 0) { // Si la IA falló
                         // Podrías añadir una clase 'miss-on-player' si quieres marcar los fallos de la IA en tu agua
                    }
                }
            }
            echo "<div class='$cell_class' data-row='$row' data-col='$col' id='$cell_id'></div>";
        }
    }
    echo "</div>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sea Battle Game</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <div class="game-header">
            <h3>Sea Battle - ¡Bienvenido, <?php echo htmlspecialchars($username); ?>!</h3>
            <a href="logout.php">Cerrar Sesión</a>
        </div>

        <input type="hidden" id="gameId" value="<?php echo $game_id; ?>">
        <input type="hidden" id="gameStatus" value="<?php echo $game_status; ?>">
        <input type="hidden" id="currentTurn" value="<?php echo $current_turn; ?>">


        <?php if ($game_status === 'setup'): ?>
        <div id="ship-placement-info">
            <h4>Fase de Colocación de Barcos</h4>
            <p>Coloca tus barcos en tu tablero. Haz clic para seleccionar el barco, luego haz clic en la celda inicial. Haz clic derecho para rotar.</p>
            <p>Barcos para colocar:</p>
            <ul id="ships-to-place-list">
                <?php foreach ($ships_config as $ship): ?>
                    <li data-name="<?php echo htmlspecialchars($ship['name']); ?>" data-size="<?php echo $ship['size']; ?>" data-count="<?php echo $ship['count']; ?>">
                        <?php echo htmlspecialchars($ship['name']); ?> (Tamaño: <?php echo $ship['size']; ?>) - <span class="count"><?php echo $ship['count']; ?></span> restantes
                    </li>
                <?php endforeach; ?>
            </ul>
            <button id="btn-random-place">Colocar Aleatoriamente</button>
            <button id="btn-confirm-placement" style="display:none;">Confirmar y Empezar Juego</button>
        </div>
        <?php endif; ?>

        <div class="game-area">
            <div class="board-container">
                <h4>Tu Tablero</h4>
                <?php render_board('player-board', $player_board_data, false, [], $opponent_shots_data); ?>
            </div>
            <div class="board-container">
                <h4>Tablero del Oponente</h4>
                <?php render_board('opponent-board', $opponent_board_data, true, $player_shots_data); ?>
            </div>
        </div>

        <div class="game-info">
            <p id="game-message">
                <?php
                if ($game_status === 'setup') echo "Coloca tus barcos.";
                elseif ($game_status === 'playing') echo ($current_turn === 'player' ? "Tu turno." : "Turno del oponente.");
                elseif ($game_status === 'player_won') echo "¡Felicidades! ¡Has ganado!";
                elseif ($game_status === 'opponent_won') echo "Fin del juego. El oponente ha ganado.";
                ?>
            </p>
        </div>
    </div>

    <script>
        const BOARD_SIZE_JS = <?php echo BOARD_SIZE; ?>; // Renombrado para evitar conflicto con la constante PHP en el mismo ámbito si se usa mal
        const shipsConfigJs = <?php echo json_encode($ships_config); ?>;
        let currentGameId = document.getElementById('gameId').value;
        // Pasamos los datos de los tableros a JS para la renderización inicial y manipulación
        let playerBoardDataJs = <?php echo json_encode($player_board_data); ?>;
        let playerShotsDataJs = <?php echo json_encode($player_shots_data); ?>;
        let opponentShotsDataJs = <?php echo json_encode($opponent_shots_data); ?>;
    </script>
    <script src="js/game.js"></script>
</body>
</html>