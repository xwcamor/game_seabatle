<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/game_logic_functions.php'; // Necesitarás este archivo

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'No autenticado']);
    exit;
}
if (!defined('BOARD_SIZE')) define('BOARD_SIZE', 10);
$ships_config_from_game = [ /* Copiar de game.php o centralizar */
    ['name' => 'Portaaviones', 'size' => 5, 'count' => 1],
    ['name' => 'Acorazado', 'size' => 4, 'count' => 1],
    ['name' => 'Crucero', 'size' => 3, 'count' => 1],
    ['name' => 'Submarino', 'size' => 3, 'count' => 1],
    ['name' => 'Destructor', 'size' => 2, 'count' => 1]
];


$data = json_decode(file_get_contents('php://input'), true);
$game_id = $data['game_id'] ?? null;
$row = $data['row'] ?? null;
$col = $data['col'] ?? null;

if ($game_id === null || $row === null || $col === null) {
    echo json_encode(['error' => 'Faltan parámetros.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM games WHERE id = ? AND user_id = ?");
$stmt->execute([$game_id, $user_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game || $game['status'] !== 'playing' || $game['current_turn'] !== 'player') {
    echo json_encode(['error' => 'Juego no válido o no es tu turno.']);
    exit;
}

$opponent_board_layout = json_decode($game['opponent_board'], true); // Barcos de la IA
$player_shots_on_ai = json_decode($game['player_shots'], true); // Disparos del jugador a la IA

if (isset($player_shots_on_ai[$row][$col])) {
    echo json_encode(['error' => 'Ya has disparado a esta celda.']);
    exit;
}

// Procesar disparo del jugador
$player_shot_result = process_shot($opponent_board_layout, $player_shots_on_ai, $row, $col, $ships_config_from_game, "opponent");
$player_shots_on_ai = $player_shot_result['updated_shots_board']; // Tablero de disparos actualizado

$response = ['player_shot_outcome' => $player_shot_result];

if ($player_shot_result['win']) {
    $stmt = $pdo->prepare("UPDATE games SET player_shots = ?, status = 'player_won', current_turn = 'none' WHERE id = ?");
    $stmt->execute([json_encode($player_shots_on_ai), $game_id]);
    echo json_encode($response);
    exit;
}

// Turno de la IA
$player_board_layout = json_decode($game['player_board'], true); // Barcos del jugador
$ai_shots_on_player = json_decode($game['opponent_shots'], true); // Disparos de la IA al jugador

$ai_turn_coords = select_ai_shot_target($ai_shots_on_player); // La IA elige dónde disparar
$ai_shot_result = process_shot($player_board_layout, $ai_shots_on_player, $ai_turn_coords['row'], $ai_turn_coords['col'], $ships_config_from_game, "player");
$ai_shots_on_player = $ai_shot_result['updated_shots_board'];

$response['ai_shot_outcome'] = $ai_shot_result;
// Añadir coordenadas del disparo de la IA a la respuesta para que el cliente sepa dónde fue
$response['ai_shot_outcome']['row'] = $ai_turn_coords['row'];
$response['ai_shot_outcome']['col'] = $ai_turn_coords['col'];


$current_game_status = 'playing';
$next_turn = 'player';

if ($ai_shot_result['win']) { // La IA ganó (jugador perdió)
    $current_game_status = 'opponent_won';
    $next_turn = 'none';
    $response['ai_shot_outcome']['loss'] = true; // Indica al cliente que el jugador perdió
}

$stmt = $pdo->prepare("UPDATE games SET player_shots = ?, opponent_shots = ?, status = ?, current_turn = ? WHERE id = ?");
$stmt->execute([json_encode($player_shots_on_ai), json_encode($ai_shots_on_player), $current_game_status, $next_turn, $game_id]);

echo json_encode($response);
?>