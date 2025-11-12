<?php
// Define o caminho para o arquivo do banco de dados (na raiz do projeto)
define('DB_PATH', __DIR__ . '/../game.db');

try {
    // 1. Conectar (ou criar) o banco de dados
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Conexão com SQLite estabelecida.<br>";

    // 2. Deletar tabela antiga se existir (para resetar)
    $pdo->exec("DROP TABLE IF EXISTS heroes;");
    echo "Tabela 'heroes' antiga dropada (se existia).<br>";

    // 3. Criar a tabela 'heroes'
    // Armazenaremos stats e equipamentos como JSON para flexibilidade
    $sql = "
    CREATE TABLE heroes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hero_id_key TEXT NOT NULL UNIQUE,     -- 'human_knight', 'dwarf_berserker'
        class_name TEXT NOT NULL,
        level INTEGER NOT NULL DEFAULT 1,
        exp INTEGER NOT NULL DEFAULT 0,
        exp_to_next_level INTEGER NOT NULL DEFAULT 50,
        hp INTEGER NOT NULL,
        base_stats_json TEXT NOT NULL,          -- JSON com {max_hp, strength, luck}
        combat_stats_json TEXT NOT NULL,        -- JSON com {attack, defense, crit_chance}
        equipment_json TEXT NOT NULL,           -- JSON com {weapon1, weapon2, ...}
        attribute_points INTEGER NOT NULL DEFAULT 0
    );
    ";

    $pdo->exec($sql);
    echo "Tabela 'heroes' criada com sucesso.<br>";
    echo "=====================================<br>";
    echo "Banco de dados inicializado!";

} catch (PDOException $e) {
    echo "Falha na inicialização do DB: " . $e->getMessage();
}