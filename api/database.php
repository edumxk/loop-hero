<?php
// Define o caminho para o arquivo do banco de dados (na raiz do projeto)
define('DB_PATH', __DIR__ . '/../game.db');

/**
 * Retorna uma instância de conexão PDO com o SQLite.
 * CRIA O BANCO E A TABELA SE NÃO EXISTIREM.
 */
function getDbConnection() {
    try {
        // 1. Tenta conectar (ou criar o arquivo .db)
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // 2. Verifica se a tabela 'heroes' existe
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS heroes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                hero_id_key TEXT NOT NULL UNIQUE,
                class_name TEXT NOT NULL,
                level INTEGER NOT NULL DEFAULT 1,
                exp INTEGER NOT NULL DEFAULT 0,
                exp_to_next_level INTEGER NOT NULL DEFAULT 50,
                hp INTEGER NOT NULL,
                base_stats_json TEXT NOT NULL,
                combat_stats_json TEXT NOT NULL,
                equipment_json TEXT NOT NULL,
                attribute_points INTEGER NOT NULL DEFAULT 0,
                current_map_id TEXT,
                current_map_pos_x INTEGER,
                current_map_pos_y INTEGER,
                gold INTEGER NOT NULL DEFAULT 0,
                potions INTEGER NOT NULL DEFAULT 3,
                
                -- Coluna de Eventos Concluídos --
                completed_events_json TEXT NOT NULL DEFAULT '{}'
            );
        ");

        return $pdo;

    } catch (PDOException $e) {
        // Envia uma resposta de erro JSON se a conexão falhar
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Falha na conexão com o banco de dados: ' . $e->getMessage()
        ]);
        die(); // Interrompe o script
    }
}