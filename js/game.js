document.addEventListener('DOMContentLoaded', () => {
    const playerBoardGridEl = document.getElementById('player-board');
    const opponentBoardGridEl = document.getElementById('opponent-board');
    const gameMessageEl = document.getElementById('game-message');
    const gameStatusInputEl = document.getElementById('gameStatus');
    const currentTurnInputEl = document.getElementById('currentTurn');
    const shipsToPlaceListEl = document.getElementById('ships-to-place-list');
    const btnConfirmPlacementEl = document.getElementById('btn-confirm-placement');
    const btnRandomPlaceEl = document.getElementById('btn-random-place');

    let currentPlacingShip = null; // { name, size, orientation: 'H' or 'V', countElement }
    let tempPlayerBoard = JSON.parse(JSON.stringify(playerBoardDataJs)); // Copia para manipulación local

    // --- Lógica de Colocación de Barcos ---
    if (gameStatusInputEl.value === 'setup') {
        initializeShipPlacement();
    } else if (gameStatusInputEl.value === 'playing') {
        document.getElementById('ship-placement-info').style.display = 'none';
        if (currentTurnInputEl.value === 'player') {
            enableOpponentBoardClicks();
        }
    }
    updateVisualBoard(playerBoardGridEl, playerBoardDataJs, false, opponentShotsDataJs);
    updateVisualBoard(opponentBoardGridEl, [], true, playerShotsDataJs);


    function initializeShipPlacement() {
        if (!playerBoardGridEl || !shipsToPlaceListEl) return;

        shipsToPlaceListEl.querySelectorAll('li').forEach(item => {
            item.addEventListener('click', () => {
                const count = parseInt(item.dataset.count);
                if (count === 0 || item.classList.contains('selected')) return;

                // Deseleccionar otros
                shipsToPlaceListEl.querySelectorAll('li').forEach(li => li.classList.remove('selected'));
                item.classList.add('selected');

                currentPlacingShip = {
                    name: item.dataset.name,
                    size: parseInt(item.dataset.size),
                    orientation: 'H', // Por defecto horizontal
                    countElement: item.querySelector('.count'),
                    listItemElement: item
                };
                gameMessageEl.textContent = `Colocando ${currentPlacingShip.name} (Tamaño: ${currentPlacingShip.size}). Haz clic en celda inicial. Clic derecho para rotar.`;
            });
        });

        playerBoardGridEl.addEventListener('click', handleCellClickPlacement);
        playerBoardGridEl.addEventListener('contextmenu', handleCellRightClickPlacement);

        if (btnRandomPlaceEl) btnRandomPlaceEl.addEventListener('click', autoPlacePlayerShips);
        if (btnConfirmPlacementEl) btnConfirmPlacementEl.addEventListener('click', confirmShipPlacement);
    }

    function handleCellClickPlacement(event) {
        if (!currentPlacingShip || gameStatusInputEl.value !== 'setup') return;
        const cell = event.target.closest('.cell');
        if (!cell) return;

        const row = parseInt(cell.dataset.row);
        const col = parseInt(cell.dataset.col);

        if (canPlaceShip(tempPlayerBoard, row, col, currentPlacingShip.size, currentPlacingShip.orientation)) {
            placeShipOnBoard(tempPlayerBoard, row, col, currentPlacingShip.size, currentPlacingShip.orientation);
            updateVisualBoard(playerBoardGridEl, tempPlayerBoard, false, {}); // Actualizar visualización

            let currentCount = parseInt(currentPlacingShip.listItemElement.dataset.count);
            currentCount--;
            currentPlacingShip.listItemElement.dataset.count = currentCount;
            currentPlacingShip.countElement.textContent = currentCount;

            if (currentCount === 0) {
                currentPlacingShip.listItemElement.classList.add('placed');
                currentPlacingShip.listItemElement.classList.remove('selected');
                currentPlacingShip = null;
                gameMessageEl.textContent = 'Barco colocado. Selecciona el siguiente o confirma.';
            } else {
                 gameMessageEl.textContent = `Coloca otro ${currentPlacingShip.name}.`;
            }
            checkAllShipsPlaced();
        } else {
            alert('No se puede colocar el barco aquí. Se superpone o está fuera de los límites.');
        }
    }

    function handleCellRightClickPlacement(event) {
        event.preventDefault();
        if (!currentPlacingShip || gameStatusInputEl.value !== 'setup') return;
        currentPlacingShip.orientation = currentPlacingShip.orientation === 'H' ? 'V' : 'H';
        gameMessageEl.textContent = `Colocando ${currentPlacingShip.name} (Tamaño: ${currentPlacingShip.size}, Orientación: ${currentPlacingShip.orientation}). Haz clic en celda inicial.`;
    }

    function canPlaceShip(board, r, c, size, orientation) {
        for (let i = 0; i < size; i++) {
            let R = r, C = c;
            if (orientation === 'H') C += i;
            else R += i;
            if (R >= BOARD_SIZE_JS || C >= BOARD_SIZE_JS || (board[R] && board[R][C] !== 0)) return false;
        }
        return true;
    }

    function placeShipOnBoard(board, r, c, size, orientation) {
        for (let i = 0; i < size; i++) {
            let R = r, C = c;
            if (orientation === 'H') C += i;
            else R += i;
            if (board[R]) board[R][C] = 1; // 1 para barco
        }
    }

    function updateVisualBoard(gridElement, boardData, isOpponent = false, shotsData = {}) {
        if (!gridElement) return;
        const cells = gridElement.querySelectorAll('.cell');
        cells.forEach(cell => {
            const r = parseInt(cell.dataset.row);
            const c = parseInt(cell.dataset.col);
            cell.className = 'cell'; // Reset

            if (isOpponent) { // Tablero del oponente (donde el jugador dispara)
                if (shotsData && shotsData[r] && shotsData[r][c]) {
                    if (shotsData[r][c] === 'H') cell.classList.add('hit');
                    else if (shotsData[r][c] === 'M') cell.classList.add('miss');
                    cell.classList.add('shot'); // Marcar como ya disparada
                }
            } else { // Tablero del jugador
                if (boardData && boardData[r] && boardData[r][c] === 1) {
                    cell.classList.add('ship');
                }
                // Marcar impactos de la IA en el tablero del jugador
                if (shotsData && shotsData[r] && shotsData[r][c]) { // shotsData aquí son los opponentShotsDataJs
                    if (shotsData[r][c] === 'H' && boardData[r][c] === 1) {
                        cell.classList.add('hit'); // La IA acertó un barco
                    } else if (shotsData[r][c] === 'M' && boardData[r][c] === 0) {
                        // Opcional: marcar fallos de la IA en el agua del jugador
                        // cell.classList.add('miss');
                    }
                }
            }
        });
    }


    function checkAllShipsPlaced() {
        const allPlaced = Array.from(shipsToPlaceListEl.querySelectorAll('li')).every(li => parseInt(li.dataset.count) === 0);
        if (allPlaced && btnConfirmPlacementEl) {
            btnConfirmPlacementEl.style.display = 'inline-block';
            gameMessageEl.textContent = 'Todos los barcos colocados. Confirma para empezar el juego.';
        }
    }

    function autoPlacePlayerShips() {
        tempPlayerBoard = Array(BOARD_SIZE_JS).fill(null).map(() => Array(BOARD_SIZE_JS).fill(0));
        shipsConfigJs.forEach(shipType => {
            for (let i = 0; i < shipType.count; i++) {
                let placed = false;
                while (!placed) {
                    const r = Math.floor(Math.random() * BOARD_SIZE_JS);
                    const c = Math.floor(Math.random() * BOARD_SIZE_JS);
                    const orientation = Math.random() < 0.5 ? 'H' : 'V';
                    if (canPlaceShip(tempPlayerBoard, r, c, shipType.size, orientation)) {
                        placeShipOnBoard(tempPlayerBoard, r, c, shipType.size, orientation);
                        placed = true;
                    }
                }
            }
        });
        updateVisualBoard(playerBoardGridEl, tempPlayerBoard, false, {});
        shipsToPlaceListEl.querySelectorAll('li').forEach(item => {
            item.dataset.count = 0;
            item.querySelector('.count').textContent = 0;
            item.classList.add('placed');
            item.classList.remove('selected');
        });
        currentPlacingShip = null;
        checkAllShipsPlaced();
        gameMessageEl.textContent = 'Barcos colocados aleatoriamente. Confirma para empezar.';
    }

    async function confirmShipPlacement() {
        try {
            const response = await fetch('api/place_ships.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ game_id: currentGameId, player_board: tempPlayerBoard })
            });
            const result = await response.json();
            if (result.success) {
                gameMessageEl.textContent = '¡Colocación confirmada! Empezando juego... Tu turno.';
                gameStatusInputEl.value = 'playing';
                currentTurnInputEl.value = 'player';
                playerBoardDataJs = JSON.parse(JSON.stringify(tempPlayerBoard)); // Guardar tablero final
                document.getElementById('ship-placement-info').style.display = 'none';
                enableOpponentBoardClicks();
            } else {
                gameMessageEl.textContent = 'Error al confirmar: ' + (result.message || 'Error desconocido');
            }
        } catch (error) {
            console.error('Error al confirmar:', error);
            gameMessageEl.textContent = 'Error al confirmar. Revisa la consola.';
        }
    }

    // --- Lógica del Juego ---
    function enableOpponentBoardClicks() {
        if (opponentBoardGridEl) {
            opponentBoardGridEl.addEventListener('click', handleOpponentCellClick);
        }
    }

    async function handleOpponentCellClick(event) {
        if (gameStatusInputEl.value !== 'playing' || currentTurnInputEl.value !== 'player') {
            gameMessageEl.textContent = "El juego no está activo o no es tu turno.";
            return;
        }
        const cell = event.target.closest('.cell');
        if (!cell || cell.classList.contains('shot')) return; // Ya disparada o no es celda

        const row = parseInt(cell.dataset.row);
        const col = parseInt(cell.dataset.col);
        cell.classList.add('shot'); // Marcar como disparada inmediatamente

        try {
            const response = await fetch('api/shoot.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ game_id: currentGameId, row: row, col: col })
            });
            const result = await response.json();

            if (result.error) {
                gameMessageEl.textContent = result.error;
                cell.classList.remove('shot'); // Permitir volver a intentar si fue error de servidor
                return;
            }

            // Actualizar tablero del oponente con el disparo del jugador
            if (result.player_shot_outcome.hit) {
                cell.classList.add('hit');
                gameMessageEl.textContent = `¡Impacto en (${row}, ${col})!`;
                if (result.player_shot_outcome.sunk) {
                    gameMessageEl.textContent += ` ¡Hundiste su ${result.player_shot_outcome.sunk}!`;
                    // Lógica para marcar todo el barco hundido visualmente (más complejo)
                }
            } else {
                cell.classList.add('miss');
                gameMessageEl.textContent = `Agua en (${row}, ${col}).`;
            }
            playerShotsDataJs[row][col] = result.player_shot_outcome.hit ? 'H' : 'M';


            if (result.player_shot_outcome.win) {
                gameMessageEl.textContent = "¡Felicidades! ¡Has hundido todos los barcos enemigos! ¡GANASTE!";
                gameStatusInputEl.value = 'player_won';
                currentTurnInputEl.value = 'none'; // Fin del juego
                return;
            }

            // Turno de la IA
            gameMessageEl.textContent += " Turno del oponente...";
            currentTurnInputEl.value = 'opponent';

            setTimeout(() => { // Simular pensamiento de la IA
                const aiShot = result.ai_shot_outcome;
                const playerCellTargetedByAI = playerBoardGridEl.querySelector(`.cell[data-row='${aiShot.row}'][data-col='${aiShot.col}']`);

                if (playerCellTargetedByAI) {
                    if (aiShot.hit) {
                        playerCellTargetedByAI.classList.add('hit'); // La IA acertó
                        gameMessageEl.textContent = `El oponente IMPACTÓ tu barco en (${aiShot.row}, ${aiShot.col})!`;
                        if (aiShot.sunk) {
                            gameMessageEl.textContent += ` ¡El oponente hundió tu ${aiShot.sunk}!`;
                        }
                    } else {
                        // Opcional: marcar fallo de la IA en el agua del jugador
                        // playerCellTargetedByAI.classList.add('miss');
                        gameMessageEl.textContent = `El oponente falló en (${aiShot.row}, ${aiShot.col}).`;
                    }
                }
                opponentShotsDataJs[aiShot.row][aiShot.col] = aiShot.hit ? 'H' : 'M';


                if (aiShot.loss) { // Jugador perdió
                    gameMessageEl.textContent = "¡Oh no! ¡El oponente hundió todos tus barcos! ¡PERDISTE!";
                    gameStatusInputEl.value = 'opponent_won';
                    currentTurnInputEl.value = 'none';
                } else {
                    gameMessageEl.textContent += " Tu turno.";
                    currentTurnInputEl.value = 'player';
                }
            }, 1500);

        } catch (error) {
            console.error('Error al procesar disparo:', error);
            gameMessageEl.textContent = 'Error al procesar disparo. Revisa la consola.';
            cell.classList.remove('shot');
        }
    }
});