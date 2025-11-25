<?php
/**
 * /api/logic/player_logic.php
 * Lida com a lógica do jogador, níveis e cálculo de status.
 */

/**
 * FUNÇÃO PRINCIPAL DE RECÁLCULO
 * Reconstrói os status de combate do zero usando a fórmula:
 * Base (Arquivo) + (Atributos * Peso) + (Level * Crescimento) + Equipamento
 */
function recalculate_player_stats($player) {
    // 1. Carrega o Template Original do Arquivo
    $all_heroes = require __DIR__ . '/../data/heroes.php';
    $hero_key = $player['hero_id_key'];
    
    if (!isset($all_heroes[$hero_key])) {
        // Fallback de segurança caso o ID não exista mais
        return $player;
    }
    
    $template = $all_heroes[$hero_key];
    
    // 2. Define os Pesos (Configuração de Balanceamento)
    // Quantos pontos de status cada atributo concede
    $WEIGHTS = [
        'strength_to_atk' => 2.0,  // 1 Força = 2 Ataque
        'strength_to_def' => 1.0,  // 1 Força = 0.5 Defesa
        'agility_to_def'  => 2.0,  // 1 Agilidade = 1 Defesa
        'agility_to_spd'  => 1.0,  // 1 Agilidade = 1 Speed (ATB)
        'luck_to_crit'    => 0.025, // 1 Sorte = 2.5% Crítico
        'luck_crit_mult'  => 0.10 // 1 Sorte = 10% Crit Multiplier
    ];

    // 3. Pega os dados atuais do jogador
    $level = $player['level'];
    $str = $player['base_stats']['strength'];
    $agi = $player['base_stats']['agility'];
    $lck = $player['base_stats']['luck'];
    $equipment = $player['equipment'];

    // 4. Recupera o Crescimento por Nível do arquivo (ou usa padrão)
    // Você deve adicionar 'growth' no heroes.php, senão usa esses defaults
    $growth_atk = $template['growth']['attack'] ?? 1; 
    $growth_def = $template['growth']['defense'] ?? 0.5;
    $growth_hp  = $template['growth']['max_hp'] ?? 10;

    // ============================================================
    // FÓRMULA DE CÁLCULO
    // ============================================================

    // -- ATAQUE --
    // Base do Arquivo + (Força * 2) + (Nível * Crescimento)
    $new_atk = ceil($template['combat_stats_base']['attack'] 
             + ($str * $WEIGHTS['strength_to_atk']) 
             + ($level * $growth_atk));

    // -- DEFESA --
    // Base do Arquivo + (Força * 0.5) + (Agilidade * 1) + (Nível * Crescimento)
    $new_def = ceil($template['combat_stats_base']['defense'] 
             + ($str * $WEIGHTS['strength_to_def'])
             + ($agi * $WEIGHTS['agility_to_def'])
             + ($level * $growth_def));

    // -- CRÍTICO --
    // Base do Arquivo + (Sorte * 0.5%)
    $new_crit = round($template['combat_stats_base']['crit_chance'] 
              + ($lck * $WEIGHTS['luck_to_crit']),2);

    $new_crit_mult = round($template['combat_stats_base']['crit_mult']
                   + ($lck * $WEIGHTS['luck_crit_mult']),2);

    $new_spd = ceil($template['base_stats']['speed']
             + ($agi * $WEIGHTS['agility_to_spd']));

    $new_hp_max = ceil(($growth_hp * $level) + $template['base_stats']['max_hp']);
    $new_HP = ceil($player['base_stats']['hp'] + $new_hp_max - $player['base_stats']['max_hp']);
    // -- HP MÁXIMO --
    // Base do Arquivo + (Vitalidade * X - se tiver atributo vit) + (Nível * 10)
    // Aqui estamos somando ao que já está no DB, mas o ideal seria recalcular tudo.
    // Para simplificar e manter sua lógica de poção de vitalidade, vamos manter o base_stats['max_hp']
    // como o valor "raw" e adicionar bônus de nível se quiser, ou deixar o checkLevelUp controlar o HP base.
    // Vou manter o HP base como está no DB para não resetar poções de vida usadas.

    // ============================================================
    // BÔNUS DE EQUIPAMENTO
    // ============================================================
    
    if (($equipment['weapon2'] ?? '') === 'shield') {
        $new_def += 7;
    }
    if (($equipment['weapon1'] ?? '') === 'greataxe') {
        $new_atk += 5;
    }
    // Adicione mais lógicas de equipamentos aqui no futuro

    // 5. Aplica os novos valores
    $player['combat_stats']['attack'] = ceil($new_atk);
    $player['combat_stats']['defense'] = ceil($new_def);
    $player['combat_stats']['crit_chance'] = $new_crit;
    $player['combat_stats']['crit_mult'] = round($new_crit_mult,2);
    $player['combat_stats']['speed'] = round($new_spd);
    $player['base_stats']['max_hp'] = ceil($new_hp_max);
    $player['base_stats']['hp'] = ceil($new_HP);

    // Atualiza também a Speed baseada na Agilidade/Speed stat se desejar
    // $player['base_stats']['speed'] = ... (se quiser recalcular speed também)

    return $player;
}

/**
 * Tenta carregar o herói do DB.
 */
function getPlayerState($pdo, $hero_id_key) {
    $stmt = $pdo->prepare("SELECT * FROM heroes WHERE hero_id_key = ?");
    $stmt->execute([$hero_id_key]);
    $hero = $stmt->fetch();

    if ($hero) {
        // Decodifica JSONs
        $hero['base_stats'] = json_decode($hero['base_stats_json'], true);
        $hero['combat_stats'] = json_decode($hero['combat_stats_json'], true);
        $hero['equipment'] = json_decode($hero['equipment_json'], true);
        $hero['completed_events'] = json_decode($hero['completed_events_json'], true);
        
        // Opcional: Recalcular sempre que carrega para garantir integridade
        // Isso corrige status se você mudar o balanceamento no heroes.php
        $hero = recalculate_player_stats($hero);
        
        return $hero;
    } else {
        return createNewPlayer($pdo, $hero_id_key);
    }
}

/**
 * Cria novo herói e salva no DB
 */
function createNewPlayer($pdo, $hero_id_key) {
    $all_heroes = require __DIR__ . '/../data/heroes.php';
    if (!isset($all_heroes[$hero_id_key])) throw new Exception("Herói inválido.");
    
    $template = $all_heroes[$hero_id_key];
    $start_map = require __DIR__ . '/../data/maps/map1.php'; 

    // Estrutura inicial provisória
    $player = [
        'hero_id_key' => $hero_id_key,
        'class_name' => $template['name'],
        'level' => 1,
        'exp' => 0,
        'exp_to_next_level' => 100,
        'hp' => $template['base_stats']['max_hp']+$template['growth']['max_hp'],
        'base_stats' => $template['base_stats'],
        'equipment' => $template['starting_equipment'],
        'combat_stats' => $template['combat_stats_base'], // Será sobrescrito logo abaixo
        'attribute_points' => 0,
        'current_map_id' => $start_map['id'],
        'current_map_pos_x' => $start_map['start_pos']['x'],
        'current_map_pos_y' => $start_map['start_pos']['y'],
        'gold' => 0,
        'potions' => 2,
        'completed_events' => []
    ];

    // Realiza o primeiro cálculo real de status
    $player = recalculate_player_stats($player);

    // Prepara para salvar
    $dbData = $player;
    $dbData['base_stats_json'] = json_encode($player['base_stats']);
    $dbData['combat_stats_json'] = json_encode($player['combat_stats']);
    $dbData['equipment_json'] = json_encode($player['equipment']);
    $dbData['completed_events_json'] = json_encode($player['completed_events']);
    
    // Remove chaves de array que não são colunas no DB para o Insert
    unset($dbData['base_stats'], $dbData['combat_stats'], $dbData['equipment'], $dbData['completed_events']);

    $columns = implode(', ', array_keys($dbData));
    $placeholders = ':' . implode(', :', array_keys($dbData));
    $sql = "INSERT INTO heroes ($columns) VALUES ($placeholders)";
    
    $pdo->prepare($sql)->execute($dbData);

    // Retorna o objeto completo (com arrays)
    return $player; 
}

/**
 * Verifica Level Up e Aplica a Nova Lógica
 */
function checkLevelUp($player) {
    $leveled_up = false;
    $log = "";
    
    if (!isset($player['attribute_points'])) $player['attribute_points'] = 0;

    // Fórmula XP: 100 * (1.5 ^ (Level - 1))
    $xp_next = round(100 * pow(1.5, $player['level'] - 1));

    while ($player['exp'] >= $xp_next) {
        $player['exp'] -= $xp_next;
        $player['level']++;
        $leveled_up = true;
        
        // Recalcula o próximo alvo de XP
        $xp_next = round(100 * pow(1.5, $player['level'] - 1));

        // --- GANHOS DO NÍVEL ---
        $player['attribute_points'] += 3; // Pontos para distribuir
        
        // Ganho automático de Status Base (opcional, se quiser auto-evolução além dos pontos)
        // Vamos dar um pouco de vida e sorte automática
        $player['base_stats']['max_hp'] += 15;
        $player['base_stats']['hp'] += 15; // Cura automática
        
        // RECALCULA OS STATUS DE COMBATE COM A NOVA FÓRMULA
        // Isso vai pegar o novo Level, novos Atributos e atualizar Atk/Def
        $player = recalculate_player_stats($player);
        
        // Cura ao subir de nível
        $player['hp'] = ceil($player['base_stats']['max_hp'] * 0.50); // Cura 50% do HP máximo ao subir de nível

        $player['exp_to_next_level'] = $xp_next;
    }

    if ($leveled_up) {
        $log = "LEVEL UP! Nível {$player['level']}! (+3 Pontos)";
    }

    return ['player' => $player, 'log' => $log];
}

/**
 * Salva o estado (Update)
 */
function savePlayerState($pdo, $player) {
    $sql = "UPDATE heroes SET
            level = :level, exp = :exp, 
            exp_to_next_level = :exp_to_next_level,
            hp = :hp, 
            base_stats_json = :base,
            combat_stats_json = :combat, 
            equipment_json = :equip,
            attribute_points = :points,
            current_map_id = :map_id,
            current_map_pos_x = :pos_x,
            current_map_pos_y = :pos_y,
            gold = :gold, potions = :potions,
            completed_events_json = :events
            WHERE hero_id_key = :id_key";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':level' => $player['level'],
        ':exp' => $player['exp'],
        ':hp' => ceil($player['hp']),
        ':base' => json_encode($player['base_stats']),
        ':combat' => json_encode($player['combat_stats']),
        ':equip' => json_encode($player['equipment']),
        ':points' => $player['attribute_points'],
        ':map_id' => $player['current_map_id'],
        ':pos_x' => $player['current_map_pos_x'],
        ':pos_y' => $player['current_map_pos_y'],
        ':gold' => $player['gold'],
        ':potions' => $player['potions'],
        ':events' => json_encode($player['completed_events']),
        ':id_key' => $player['hero_id_key'],
        ':exp_to_next_level' => $player['exp_to_next_level']
    ]);
}
?>