<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/game_logic_functions.php'; // Necesitarás este archivo

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$game_id = $data['game_id'] ?? null;
$player_board_array = $data['player_board'] ?? null;

if (!$game_id || !$player_board_array) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos del juego o del tablero.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Validar que el juego pertenece al usuario y está en 'setup'
// (código de validación omitido por brevedad)

$player_board_json = json_encode($player_board_array);

// Generar tablero de la IA
if (!defined('BOARD_SIZE')) define('BOARD_SIZE', 10); // Asegurar que está definida
$ships_config_from_game = [ /* Copiar de game.php o centralizar */
    ['name' => 'Portaaviones', 'size' => 5, 'count' => 1],
    ['name' => 'Acorazado', 'size' => 4, 'count' => 1],
    ['name' => 'Crucero', 'size' => 3, 'count' => 1],
    ['name' => 'Submarino', 'size' => 3, 'count' => 1],
    ['name' => 'Destructor', 'size' => 2, 'count' => 1]
];
$ai_board_array = generate_random_ship_placements(BOARD_SIZE, $ships_config_from_game);
$ai_board_json = json_encode($ai_board_array);

$empty_shots_json = json_encode(array_fill(0, BOARD_SIZE, array_fill(0, BOARD_SIZE, null)));

$stmt = $pdo->prepare("UPDATE games SET player_board = ?, opponent_board = ?, player_shots = ?, opponent_shots = ?, status = 'playing', current_turn = 'player' WHERE id = ? AND user_id = ?");
if ($stmt->execute([$player_board_json, $ai_board_json, $empty_shots_json, $empty_shots_json, $game_id, $user_id])) {
    echo json_encode(['success' => true, 'message' => 'Colocación confirmada. Juego iniciado.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al actualizar el juego.']);
}
?>