<?php
/**
 * /api/logic/monster_logic.php
 * Lógica de Spawn que respeita a configuração do monstros.php
 */

function spawnMonster($player_level, $difficulty = 'easy', $monster_id = null) {
    
    $all_monsters = require __DIR__ . '/../data/monsters.php';
    
    if (empty($all_monsters)) {
        throw new Exception("Erro Crítico: 'monsters.php' vazio.");
    }

    $template = null;

    // 1. Seleção
    if ($monster_id !== null && isset($all_monsters[$monster_id])) {
        $template = $all_monsters[$monster_id];
    } else {
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

    // 2. Nível e Dificuldade
    $difficulty_bonus = 0;
    if ($difficulty === 'medium') $difficulty_bonus = 1;
    if ($difficulty === 'hard') $difficulty_bonus = 2;
    
    // Player Level + Dificuldade +/- 1 de variação
    $monster_level = max(1, $player_level + $difficulty_bonus + rand(-1, 1));
    
    // Quantos níveis ele tem acima do "nascimento" dele?
    $level_gap = max(0, $monster_level - $template['base_level']);

    // 3. Cálculo de Status (Obedecendo monstros.php)
    $final_stats = $template['stats'];
    $final_stats['level'] = $monster_level;
    
    // Valores padrão ATB
    if (!isset($final_stats['speed'])) $final_stats['speed'] = 8;
    if (!isset($final_stats['potions'])) $final_stats['potions'] = 0;

    // APLICAÇÃO DA ESCALA
    if ($level_gap > 0) {
        // Simplesmente multiplicamos o gap pelos valores definidos no arquivo de configuração
        $growth = $template['stats_per_level'];

        $final_stats['max_hp'] += ($level_gap * ($growth['max_hp'] ?? 10));
        $final_stats['attack'] += ($level_gap * ($growth['attack'] ?? 2));
        $final_stats['defense'] += ($level_gap * ($growth['defense'] ?? 0.5));
    }
    
    // O HP atual começa cheio
    $final_stats['max_hp'] = floor($final_stats['max_hp']);
    $final_stats['attack'] = floor($final_stats['attack']);
    $final_stats['defense'] = floor($final_stats['defense']); // Arredonda defesa (ex: 0.5 vira 0, 1.5 vira 1)
    
    // 4. Recompensas
    $base_exp = $template['base_exp'] ?? 10;
    $base_gold = $template['base_gold'] ?? 5;
    
    // XP escala progressivamente com o nível
    $exp_reward = floor($base_exp + ($level_gap * $base_exp * 0.25));
    $gold_reward = floor($base_gold + ($level_gap * 2));

    return [
        'name' => $template['name'] . " (Lvl $monster_level)",
        'hp' => $final_stats['max_hp'],
        'stats' => $final_stats,
        'sprite_folder' => $template['sprite_folder'],
        'scale' => $template['scale'] ?? 1.0,
        'exp_reward' => $exp_reward,
        'gold_reward' => $gold_reward
    ];
}
?>