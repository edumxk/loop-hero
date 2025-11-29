<?php
/**
 * /api/logic/player_logic.php
 * Lógica do Jogador, Status e Banco de Dados.
 */

// 1. Recalcula Combat Stats baseado nos Atributos Atuais
function recalculate_player_stats($player) {
    $all_heroes = require __DIR__ . '/../data/heroes.php';
    $hero_key = $player['hero_id_key'];
    
    // Se não achar o template, retorna o player como está para não quebrar
    if (!isset($all_heroes[$hero_key])) return $player;
    $template = $all_heroes[$hero_key];
    
    // --- PESOS DE ATRIBUTOS ---
    $WEIGHTS = [
        'strength_to_atk' => 2.0, 
        'strength_to_def' => 0.5,
        'agility_to_def'  => 1.0, 
        'agility_to_spd'  => 1.0, // 1 Agilidade = 1 Velocidade Extra
        'luck_to_crit'    => 0.05, 
        'luck_crit_mult'  => 0.15
    ];

    // --- LEITURA DOS ATRIBUTOS ATUAIS ---
    $level = $player['level'];
    
    // Garante que os atributos existam (inicia com 0 se nulo)
    $str = $player['base_stats']['strength'] ?? 0;
    $agi = $player['base_stats']['agility'] ?? 0; // <--- CORREÇÃO: Lê 'agility'
    $lck = $player['base_stats']['luck'] ?? 0;
    $equipment = $player['equipment'] ?? [];

    // Valores Base do Template (Se o JSON do player não tiver 'speed' base, pega do template)
    $base_speed = $player['base_stats']['speed'] ?? $template['base_stats']['speed'] ?? 10;

    // Crescimento por Nível
    $growth_atk = $template['growth']['attack'] ?? 1; 
    $growth_def = $template['growth']['defense'] ?? 0.5;

    // --- CÁLCULOS ---

    // Ataque
    $new_atk = $template['combat_stats_base']['attack'] 
             + ($str * $WEIGHTS['strength_to_atk']) 
             + ($level * $growth_atk);

    // Defesa
    $new_def = $template['combat_stats_base']['defense'] 
             + ($str * $WEIGHTS['strength_to_def']) 
             + ($agi * $WEIGHTS['agility_to_def']) 
             + ($level * $growth_def);

    // Crítico
    $new_crit = $template['combat_stats_base']['crit_chance'] 
              + ($lck * $WEIGHTS['luck_to_crit']);
              
    $new_crit_mult = ($template['combat_stats_base']['crit_mult'] ?? 1.5) 
                   + ($lck * $WEIGHTS['luck_crit_mult']);

    // Velocidade (Speed)
    // Fórmula: Velocidade Base + (Agilidade * Peso)
    $new_spd = $base_speed + ($agi * $WEIGHTS['agility_to_spd']);

    // --- BÔNUS DE EQUIPAMENTO ---
    if (($equipment['weapon2'] ?? '') === 'shield') $new_def += 5;
    if (($equipment['weapon1'] ?? '') === 'greataxe') $new_atk += 5;

    // --- SALVAR NO ARRAY DE COMBATE ---
    $player['combat_stats']['attack'] = round($new_atk, 1);
    $player['combat_stats']['defense'] = round($new_def, 1);
    $player['combat_stats']['crit_chance'] = round($new_crit, 2);
    $player['combat_stats']['crit_mult'] = round($new_crit_mult, 2);
    
    // CORREÇÃO: Adiciona o Speed no JSON de combate
    $player['combat_stats']['speed'] = round($new_spd, 1); 

    return $player;
}

// 2. Busca Herói (Filtra por ID do Herói e ID do Usuário)
function getPlayerState($pdo, $hero_id_key, $user_id) {
    // IMPORTANTE: Busca pelo par (hero_id_key, user_id)
    $stmt = $pdo->prepare("SELECT * FROM heroes WHERE hero_id_key = ? AND user_id = ?");
    $stmt->execute([$hero_id_key, $user_id]);
    $hero = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($hero) {
        // Decodifica JSONs
        $hero['base_stats'] = json_decode($hero['base_stats_json'], true);
        $hero['combat_stats'] = json_decode($hero['combat_stats_json'], true);
        $hero['equipment'] = json_decode($hero['equipment_json'], true);
        $hero['completed_events'] = json_decode($hero['completed_events_json'], true);
        
        // Garante que o cálculo esteja sempre atualizado ao carregar
        $hero = recalculate_player_stats($hero);
        return $hero;
    } else {
        return createNewPlayer($pdo, $hero_id_key, $user_id);
    }
}

// 3. Cria Novo Herói
function createNewPlayer($pdo, $hero_id_key, $user_id) {
    $all_heroes = require __DIR__ . '/../data/heroes.php';
    if (!isset($all_heroes[$hero_id_key])) throw new Exception("Herói inválido.");
    
    $template = $all_heroes[$hero_id_key];
    $start_map = require __DIR__ . '/../data/maps/map1.php'; 

    $player = [
        'user_id' => $user_id,
        'hero_id_key' => $hero_id_key,
        'class_name' => $template['name'],
        'level' => 1,
        'exp' => 0,
        'exp_to_next_level' => 50,
        'hp' => $template['base_stats']['max_hp'],
        'base_stats' => $template['base_stats'],
        'equipment' => $template['starting_equipment'],
        'combat_stats' => $template['combat_stats_base'],
        'attribute_points' => 0,
        'current_map_id' => $start_map['id'],
        'current_map_pos_x' => $start_map['start_pos']['x'],
        'current_map_pos_y' => $start_map['start_pos']['y'],
        'gold' => 0,
        'potions' => 2,
        'completed_events' => []
    ];

    $player = recalculate_player_stats($player);

    $dbData = $player;
    $dbData['base_stats_json'] = json_encode($player['base_stats']);
    $dbData['combat_stats_json'] = json_encode($player['combat_stats']);
    $dbData['equipment_json'] = json_encode($player['equipment']);
    $dbData['completed_events_json'] = json_encode($player['completed_events']);
    
    unset($dbData['base_stats'], $dbData['combat_stats'], $dbData['equipment'], $dbData['completed_events']);

    $columns = implode(', ', array_keys($dbData));
    $placeholders = ':' . implode(', :', array_keys($dbData));
    
    $sql = "INSERT INTO heroes ($columns) VALUES ($placeholders)";
    $pdo->prepare($sql)->execute($dbData);
    
    // Captura o ID gerado (Primary Key) para garantir saves futuros corretos
    $player['id'] = $pdo->lastInsertId();

    return $player; 
}

// 4. Salva o Estado (CORREÇÃO CRÍTICA AQUI)
function savePlayerState($pdo, $player) {
    // AQUI ESTAVA O ERRO: Nunca use 'WHERE hero_id_key = ...' pois afeta todos os usuários.
    // Use 'WHERE id = :id' (Chave Primária única deste save específico).
    
    $sql = "UPDATE heroes SET 
            level = :level, 
            exp = :exp, 
            exp_to_next_level = :exp_to_next_level,
            hp = :hp, 
            base_stats_json = :base,
            combat_stats_json = :combat, 
            equipment_json = :equip,
            attribute_points = :points,
            current_map_id = :map_id,
            current_map_pos_x = :pos_x,
            current_map_pos_y = :pos_y,
            gold = :gold, 
            potions = :potions,
            completed_events_json = :events
            WHERE id = :id AND user_id = :user_id"; 
            // Adicionei AND user_id como segurança extra, embora id seja único.
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':level' => $player['level'],
        ':exp' => $player['exp'],
        ':exp_to_next_level' => $player['exp_to_next_level'], // Faltava este campo às vezes
        ':hp' => $player['hp'],
        ':base' => json_encode($player['base_stats']), // Salva o array modificado
        ':combat' => json_encode($player['combat_stats']), // Salva o array recalculado
        ':equip' => json_encode($player['equipment']),
        ':points' => $player['attribute_points'],
        ':map_id' => $player['current_map_id'],
        ':pos_x' => $player['current_map_pos_x'],
        ':pos_y' => $player['current_map_pos_y'],
        ':gold' => $player['gold'],
        ':potions' => $player['potions'],
        ':events' => json_encode($player['completed_events']),
        ':id' => $player['id'], // ID ÚNICO DO ROW
        ':user_id' => $player['user_id'] // SEGURANÇA
    ]);
}

// 5. Level Up
function checkLevelUp($player) {
    $leveled_up = false;
    $log = "";
    
    if (!isset($player['attribute_points'])) $player['attribute_points'] = 0;

    // Garante exp_to_next_level
    if (!isset($player['exp_to_next_level']) || $player['exp_to_next_level'] <= 0) {
        $player['exp_to_next_level'] = round(100 * pow(1.5, $player['level']));
    }

    while ($player['exp'] >= $player['exp_to_next_level']) {
        $player['exp'] -= $player['exp_to_next_level'];
        $player['level']++;
        $leveled_up = true;
        
        $player['exp_to_next_level'] = round(100 * pow(1.5, $player['level']));
        
        // Ganhos
        $player['attribute_points'] += 3; 
        $player['base_stats']['max_hp'] += 15; 
        
        $player = recalculate_player_stats($player);
        $player['hp'] = $player['base_stats']['max_hp']; // Cura total
    }

    if ($leveled_up) $log = "LEVEL UP! Nível {$player['level']}!";
    return ['player' => $player, 'log' => $log];
}
?>