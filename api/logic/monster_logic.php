<?php
/**
 * /api/logic/monster_logic.php
 * Lida com a criação e escala de monstros.
 */

// A função deve aceitar 3 argumentos
function spawnMonster($player_level, $difficulty = 'easy', $monster_id = null) {
    
    $all_monsters = require __DIR__ . '/../data/monsters.php';
    
    if (empty($all_monsters)) {
        throw new Exception("Erro Crítico: 'monsters.php' está vazio ou corrompido.");
    }

    $template = null;

    // Seleção de Monstro
    if ($monster_id !== null && isset($all_monsters[$monster_id])) {
        $template = $all_monsters[$monster_id];
    } 
    else {
        $spawnable_monsters = [];
        foreach ($all_monsters as $id => $data) {
            if ($data['base_level'] <= $player_level + 2) {
                $spawnable_monsters[$id] = $data;
            }
        }
        if (empty($spawnable_monsters)) $spawnable_monsters = $all_monsters;
        $id = array_rand($spawnable_monsters);
        $template = $spawnable_monsters[$id];
    }

    // Dificuldade
    $difficulty_bonus = 0;
    if ($difficulty === 'medium') $difficulty_bonus = 1;
    if ($difficulty === 'hard') $difficulty_bonus = 2;
    
    $base_fight_level = $player_level + $difficulty_bonus;
    $monster_level = max(1, $base_fight_level + rand(-1, 1));
    $level_gap = $monster_level - $template['base_level'];

    // Validações
    if (!isset($template['stats'])) throw new Exception("Monstro sem stats.");

    $final_stats = $template['stats'];
    $final_stats['level'] = $monster_level;
    
    // Defaults para ATB
    if (!isset($final_stats['speed'])) $final_stats['speed'] = 8;
    if (!isset($final_stats['potions'])) $final_stats['potions'] = 0;

    // Escalonamento
    if ($level_gap > 0) {
        $final_stats['max_hp'] += $level_gap * $template['stats_per_level']['max_hp'];
        $final_stats['attack'] += $level_gap * $template['stats_per_level']['attack'];
        $final_stats['defense'] += $level_gap * $template['stats_per_level']['defense'];
    }
    
    $exp_reward = floor(($template['base_exp'] ?? 10) * (1 + ($level_gap * 0.1)));
    $gold_reward = floor(($template['base_gold'] ?? 5) * (1 + ($level_gap * 0.1)));

    return [
        'name' => $template['name'] . " (Lvl $monster_level)",
        'hp' => $final_stats['max_hp'],
        'stats' => $final_stats,
        'sprite_folder' => $template['sprite_folder'], 
        'exp_reward' => $exp_reward,
        'gold_reward' => $gold_reward
    ];
}
?>