<?php
/**
 * /api/logic/player_logic.php
 * Lida com o Herói (DB e Cálculos)
 * (Versão corrigida e limpa)
 */

/**
 * Calcula os stats de combate (ATK, DEF) com base nos atributos (STR, LCK)
 */
function calculate_combat_stats($base_stats, $combat_stats_base, $equipment) {
    $combat_stats = $combat_stats_base; // Pega 'attack', 'defense', etc. base

    // Força aumenta ataque e defesa
    $combat_stats['attack']  += floor($base_stats['strength'] * 1);
    $combat_stats['defense'] += floor($base_stats['strength'] * 0.5);

    // Sorte aumenta chance de crítico
    $combat_stats['crit_chance'] += $base_stats['luck'] * 0.01;

    // TODO: Adicionar bônus de equipamento
    if ($equipment['weapon2'] === 'shield') {
        $combat_stats['defense'] += 3; // Bônus de escudo
    }

    return $combat_stats;
}

/**
 * Tenta carregar o herói do DB. Se não existir, cria um novo.
 */
function getPlayerState($pdo, $hero_id_key) {
    $stmt = $pdo->prepare("SELECT * FROM heroes WHERE hero_id_key = ?");
    $stmt->execute([$hero_id_key]);
    $hero = $stmt->fetch();

    if ($hero) {
        // Herói existe, decodifica o JSON
        $hero['base_stats'] = json_decode($hero['base_stats_json'], true);
        $hero['combat_stats'] = json_decode($hero['combat_stats_json'], true);
        $hero['equipment'] = json_decode($hero['equipment_json'], true);
        // Decodifica os eventos concluídos
        $hero['completed_events'] = json_decode($hero['completed_events_json'], true);
        return $hero;
    } else {
        // Herói não existe, cria um novo
        return createNewPlayer($pdo, $hero_id_key);
    }
}

/**
 * Cria um novo herói (Nível 1) no banco de dados.
 */
function createNewPlayer($pdo, $hero_id_key) {
    $all_heroes = require __DIR__ . '/../data/heroes.php';
    if (!isset($all_heroes[$hero_id_key])) {
        throw new Exception("ID de Herói inválido: $hero_id_key");
    }
    $template = $all_heroes[$hero_id_key];

    // Carrega o mapa inicial para pegar a posição 'S'
    // (Presume que o mapa 'map1.php' existe)
    $start_map = require __DIR__ . '/../data/maps/map1.php'; 

    // ================================================================
    // A CORREÇÃO ESTÁ AQUI
    // ================================================================
    // O placeholder '/*...*/' foi substituído pelos parâmetros corretos.
    $combat_stats = calculate_combat_stats(
        $template['base_stats'],
        $template['combat_stats_base'],
        $template['starting_equipment']
    );
    // ================================================================

    $newHero = [
        'hero_id_key' => $hero_id_key,
        'class_name' => $template['name'],
        'level' => 1,
        'exp' => 0,
        'exp_to_next_level' => 50,
        'hp' => $template['base_stats']['max_hp'],
        'base_stats_json' => json_encode($template['base_stats']),
        'combat_stats_json' => json_encode($combat_stats),
        'equipment_json' => json_encode($template['starting_equipment']),
        'attribute_points' => 0,
        'current_map_id' => $start_map['id'],
        'current_map_pos_x' => $start_map['start_pos']['x'],
        'current_map_pos_y' => $start_map['start_pos']['y'],
        'gold' => 10,
        'potions' => 3,
        'completed_events_json' => '{}' // Começa com JSON vazio
    ];

    // Lógica de INSERT dinâmica
    $columns = implode(', ', array_keys($newHero));
    $placeholders = ':' . implode(', :', array_keys($newHero));
    $sql = "INSERT INTO heroes ($columns) VALUES ($placeholders)";
    
    $pdo->prepare($sql)->execute($newHero);

    return getPlayerState($pdo, $hero_id_key);
}

/**
 * Salva o estado atualizado do herói no DB (agora com posição e eventos)
 */
function savePlayerState($pdo, $playerData) {
    // Garante que os dados JSON estão como string
    $playerData['base_stats_json'] = json_encode($playerData['base_stats']);
    $playerData['combat_stats_json'] = json_encode($playerData['combat_stats']);
    $playerData['equipment_json'] = json_encode($playerData['equipment']);
    // Codifica os eventos concluídos
    $playerData['completed_events_json'] = json_encode($playerData['completed_events']);

    $sql = "UPDATE heroes SET
                level = :level, exp = :exp, exp_to_next_level = :exp_to_next_level,
                hp = :hp, base_stats_json = :base_stats_json,
                combat_stats_json = :combat_stats_json, equipment_json = :equipment_json,
                attribute_points = :attribute_points,
                current_map_id = :current_map_id,
                current_map_pos_x = :current_map_pos_x,
                current_map_pos_y = :current_map_pos_y,
                gold = :gold, potions = :potions,
                completed_events_json = :completed_events_json
            WHERE id = :id";
    
    // Prepara um array limpo para execução
    $exec_data = [
        ':level' => $playerData['level'],
        ':exp' => $playerData['exp'],
        ':exp_to_next_level' => $playerData['exp_to_next_level'],
        ':hp' => $playerData['hp'],
        ':base_stats_json' => $playerData['base_stats_json'],
        ':combat_stats_json' => $playerData['combat_stats_json'],
        ':equipment_json' => $playerData['equipment_json'],
        ':attribute_points' => $playerData['attribute_points'],
        ':current_map_id' => $playerData['current_map_id'],
        ':current_map_pos_x' => $playerData['current_map_pos_x'],
        ':current_map_pos_y' => $playerData['current_map_pos_y'],
        ':gold' => $playerData['gold'],
        ':potions' => $playerData['potions'],
        ':completed_events_json' => $playerData['completed_events_json'],
        ':id' => $playerData['id']
    ];
    
    $pdo->prepare($sql)->execute($exec_data);
}

/**
 * Verifica se o jogador subiu de nível.
 */
function checkLevelUp($player) {
    $log = "";
    if ($player['exp'] >= $player['exp_to_next_level']) {
        $player['level']++;
        $player['exp'] -= $player['exp_to_next_level']; 
        $player['exp_to_next_level'] = floor($player['exp_to_next_level'] * 1.5 * $player['level']);
        $player['attribute_points'] += 1; 
        
        $player['base_stats']['max_hp'] += 10;
        $player['base_stats']['strength'] += 1;
        $player['base_stats']['luck'] += 1;
    
        
        $heal_amount = floor($player['base_stats']['max_hp'] * 0.3);
        $player['hp'] = min($player['base_stats']['max_hp'], $player['hp'] + $heal_amount); 

        // Recalcula stats de combate (usa o array 'combat_stats' como 'base' de combate)
        $player['combat_stats'] = calculate_combat_stats(
            $player['base_stats'], 
            $player['combat_stats'], // Passa os stats de combate atuais como base
            $player['equipment']
        );
        
        $log = "VOCÊ SUBIU PARA O NÍVEL {$player['level']}! (Saúde recuperada)";
    }
    return ['player' => $player, 'log' => $log];
}