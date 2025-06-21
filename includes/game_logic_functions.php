<?php
// includes/game_logic_functions.php

if (!defined('BOARD_SIZE')) {
    define('BOARD_SIZE', 10);
}

function can_place_ship_server($board, $r, $c, $size, $orientation) {
    for ($i = 0; $i < $size; $i++) {
        $cur_r = $r; $cur_c = $c;
        if ($orientation === 'H') $cur_c += $i; else $cur_r += $i;
        if ($cur_r >= BOARD_SIZE || $cur_c >= BOARD_SIZE || !isset($board[$cur_r][$cur_c]) || $board[$cur_r][$cur_c] != 0) {
            return false;
        }
    }
    return true;
}

function place_ship_on_board_server(&$board, $r, $c, $size, $orientation, $ship_id_value = 1) {
    for ($i = 0; $i < $size; $i++) {
        $cur_r = $r; $cur_c = $c;
        if ($orientation === 'H') $cur_c += $i; else $cur_r += $i;
        $board[$cur_r][$cur_c] = $ship_id_value;
    }
}

function generate_random_ship_placements($board_size_param, $ships_config_param) {
    $board = array_fill(0, $board_size_param, array_fill(0, $board_size_param, 0));
    $placed_ship_details = []; // Para almacenar detalles de barcos colocados

    foreach ($ships_config_param as $ship_type_idx => $ship_type) {
        for ($count = 0; $count < $ship_type['count']; $count++) {
            $placed = false;
            $ship_id = "s{$ship_type_idx}_{$count}"; // ID único para este barco: s0_0, s0_1, s1_0 etc.
            
            while (!$placed) {
                $r = rand(0, $board_size_param - 1);
                $c = rand(0, $board_size_param - 1);
                $orientation = (rand(0, 1) == 0) ? 'H' : 'V';
                if (can_place_ship_server($board, $r, $c, $ship_type['size'], $orientation)) {
                    // Guardar las coordenadas de este barco
                    $current_ship_coords = [];
                    for ($i = 0; $i < $ship_type['size']; $i++) {
                        $cur_r = $r; $cur_c = $c;
                        if ($orientation === 'H') $cur_c += $i; else $cur_r += $i;
                        $board[$cur_r][$cur_c] = $ship_id; // Usar ID de barco en lugar de 1
                        $current_ship_coords[] = ['r' => $cur_r, 'c' => $cur_c];
                    }
                    $placed_ship_details[$ship_id] = [
                        'name' => $ship_type['name'],
                        'size' => $ship_type['size'],
                        'coords' => $current_ship_coords,
                        'hits' => 0
                    ];
                    $placed = true;
                }
            }
        }
    }
    // Devolvemos tanto el tablero visual (con IDs) como los detalles de los barcos
    // En la DB, opponent_board podría guardar $board, y una nueva columna opponent_ship_details podría guardar $placed_ship_details (JSON)
    // Por simplicidad aquí, solo devolvemos el tablero con IDs. La lógica de hundimiento necesitará los detalles.
    // Para este ejemplo, vamos a simplificar y solo devolver el tablero con 1s para la IA.
    // La lógica de hundimiento se vuelve más compleja si solo tenemos 1s.
    $simple_board = array_fill(0, $board_size_param, array_fill(0, $board_size_param, 0));
    foreach($board as $row_idx => $row_val) {
        foreach($row_val as $col_idx => $cell_val) {
            if ($cell_val !== 0) $simple_board[$row_idx][$col_idx] = 1; // Marcar como 1 si hay cualquier barco
        }
    }
    return $simple_board; // Devolver tablero simple con 1s para barcos
}


function process_shot($target_ship_layout_board, $current_shots_board, $row, $col, $ships_config_param, $target_player_type) {
    // $target_player_type es "player" o "opponent", para saber qué detalles de barcos buscar en la sesión/DB si es necesario para hundimiento.
    // Esta función es ahora más simple porque generate_random_ship_placements devuelve un tablero con 1s.
    // La lógica de hundimiento y victoria es un placeholder.
    
    $hit = false;
    if (isset($target_ship_layout_board[$row][$col]) && $target_ship_layout_board[$row][$col] == 1) {
        $hit = true;
        $current_shots_board[$row][$col] = 'H';
    } else {
        $current_shots_board[$row][$col] = 'M';
    }

    $sunk_ship_name = null;
    $all_sunk = false;

    // LÓGICA DE HUNDIMIENTO Y VICTORIA (MUY SIMPLIFICADA - NECESITA MEJORARSE)
    // Para una lógica real, necesitarías el layout detallado de los barcos (no solo 0s y 1s)
    // y rastrear los impactos en cada barco específico.
    if ($hit) {
        // Contar todos los '1' en el target_ship_layout_board
        $total_ship_parts = 0;
        foreach ($target_ship_layout_board as $r_val) {
            foreach ($r_val as $c_val) {
                if ($c_val == 1) $total_ship_parts++;
            }
        }
        // Contar todos los 'H' en current_shots_board
        $total_hits = 0;
        foreach ($current_shots_board as $r_val) {
            foreach ($r_val as $c_val) {
                if ($c_val == 'H') $total_hits++;
            }
        }
        if ($total_hits >= $total_ship_parts && $total_ship_parts > 0) { // Asegurarse que había barcos
            $all_sunk = true;
        }
        // La detección de un barco específico hundido es más compleja y se omite aquí.
    }


    return [
        'hit' => $hit,
        'sunk' => $sunk_ship_name, // Placeholder
        'win' => $all_sunk,       // Placeholder
        'updated_shots_board' => $current_shots_board
    ];
}

function select_ai_shot_target($ai_shots_on_player_board) {
    // IA simple: disparo aleatorio en una celda no atacada
    $available_cells = [];
    for ($r = 0; $r < BOARD_SIZE; $r++) {
        for ($c = 0; $c < BOARD_SIZE; $c++) {
            if (!isset($ai_shots_on_player_board[$r][$c])) {
                $available_cells[] = ['row' => $r, 'col' => $c];
            }
        }
    }
    if (empty($available_cells)) return ['row' => 0, 'col' => 0]; // No debería pasar si el juego no ha terminado
    return $available_cells[array_rand($available_cells)];
}
?>