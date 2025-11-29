<?php
// Define o caminho para o arquivo do banco de dados (na raiz do projeto)
define('DB_PATH', __DIR__ . '/../game.db');

/**
 * Retorna uma instância de conexão PDO com o SQLite.
 * CRIA O BANCO E A TABELA SE NÃO EXISTIREM.
 */
function getDbConnection() {
    $dbExists = file_exists(__DIR__ . '/../game.db');
    $pdo = new PDO('sqlite:' . __DIR__ . '/../game.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (!$dbExists) {
        // 1. Tabela de Usuários (Nova)
        $pdo->exec("CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            cpf TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL
        )");

        // 2. Tabela de Heróis (Atualizada com user_id)
        $pdo->exec("CREATE TABLE heroes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL, -- Vínculo com o dono do save
            hero_id_key TEXT,
            class_name TEXT,
            level INTEGER,
            exp INTEGER,
            exp_to_next_level INTEGER,
            hp INTEGER,
            base_stats_json TEXT,
            combat_stats_json TEXT,
            equipment_json TEXT,
            attribute_points INTEGER,
            current_map_id TEXT,
            current_map_pos_x INTEGER,
            current_map_pos_y INTEGER,
            gold INTEGER,
            potions INTEGER,
            completed_events_json TEXT,
            FOREIGN KEY(user_id) REFERENCES users(id)
        )");
    }
    return $pdo;
}