<?php
/**
 * /api/logic/monster_logic.php
 * Lida com a criação e escala de monstros.
 */function spawnMonster($player_level, $difficulty = 'easy', $monster_id = null) {
    
    $all_monsters = require __DIR__ . '/../data/monsters.php';
    if (empty($all_monsters)) {
        throw new Exception("Erro Crítico: 'monsters.php' está vazio ou corrompido.");
    }

    // ================================================================
    // A CORREÇÃO ESTÁ AQUI (Lógica de Spawn Específico)
    // ================================================================
    
    $template = null;

    // 1. Se um ID foi fornecido (e ele existe), use-o.
    if ($monster_id !== null && isset($all_monsters[$monster_id])) {
        
        $template = $all_monsters[$monster_id];

    } else {
        // 2. Senão, use a lógica aleatória antiga
        // (Filtrando monstros que o jogador pode enfrentar)
        $spawnable_monsters = [];
        foreach ($all_monsters as $id => $data) {
            // (Esta lógica de filtro pode ser melhorada, 
            // mas por enquanto funciona)
            if ($data['base_level'] <= $player_level + 2) {
                $spawnable_monsters[$id] = $data;
            }
        }
        
        if (empty($spawnable_monsters)) {
            $spawnable_monsters = $all_monsters; // Fallback
        }

        $id = array_rand($spawnable_monsters);
        $template = $spawnable_monsters[$id];
    }
    // ================================================================

    // 3. Define o "valor de dificuldade"
    $difficulty_bonus = 0;
    if ($difficulty === 'medium') {
        $difficulty_bonus = 1;
    } elseif ($difficulty === 'hard') {
        $difficulty_bonus = 2;
    }
    
    // 4. Calcula o nível final do monstro (usando a dificuldade)
    $base_fight_level = $player_level + $difficulty_bonus;
    $level_diff_rng = rand(-1, 1);
    $monster_level = max(1, $base_fight_level + $level_diff_rng);
    
    // 5. Calcula o "gap" para escalar stats
    $level_gap = $monster_level - $template['base_level'];

    // ... (verificações de 'stats_per_level' e 'base_exp') ...
    if (!isset($template['stats_per_level'])) throw new Exception("Monstro '{$template['name']}' sem 'stats_per_level'.");
    if (!isset($template['base_exp'])) throw new Exception("Monstro '{$template['name']}' sem 'base_exp'.");
    if (!isset($template['base_gold'])) throw new Exception("Monstro '{$template['name']}' sem 'base_gold'.");

    $final_stats = $template['stats'];
    $final_stats['level'] = $monster_level;

    // Aplica bônus de escala
    if ($level_gap > 0) {
        $final_stats['max_hp'] += $level_gap * $template['stats_per_level']['max_hp'];
        $final_stats['attack'] += $level_gap * $template['stats_per_level']['attack'];
        $final_stats['defense'] += $level_gap * $template['stats_per_level']['defense'];
    }
    
    // Calcula recompensas escalonadas
    $exp_reward = $template['base_exp'] * (1 + ($level_gap * 0.1));
    $gold_reward = $template['base_gold'] * (1 + ($level_gap * 0.1));

    return [
        'name' => $template['name'] . " (Lvl $monster_level)",
        'hp' => $final_stats['max_hp'],
        'stats' => $final_stats,
        'sprite' => $template['sprite_url'],
        'exp_reward' => floor($exp_reward),
        'gold_reward' => floor($gold_reward)
    ];
}