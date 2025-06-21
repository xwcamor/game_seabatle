
CREATE DATABASE IF NOT EXISTS seabatle_game CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE seabatle_game;

-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de partidas
CREATE TABLE IF NOT EXISTS games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    status ENUM('ongoing', 'won', 'lost') DEFAULT 'ongoing',
    score INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabla de disparos (si se desea guardar historial de jugadas)
CREATE TABLE IF NOT EXISTS shots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    position VARCHAR(10) NOT NULL,
    result ENUM('hit', 'miss') NOT NULL,
    shot_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
);
