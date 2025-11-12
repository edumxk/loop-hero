<?php
/**
 * /api/logic/monster_logic.php
 * Lida com a criação e escala de monstros.
 */
function spawnMonster($player_level, $difficulty = 'easy') { // <-- Assinatura atualizada
    
    // ... (verificações de arquivo e $all_monsters) ...
    $all_monsters = require __DIR__ . '/../data/monsters.php';
    // ... (lógica de filtro $spawnable_monsters) ...
    if (empty($spawnable_monsters)) { $spawnable_monsters = $all_monsters; }

    $id = array_rand($spawnable_monsters);
    $template = $spawnable_monsters[$id];

    // ================================================================
    // A CORREÇÃO ESTÁ AQUI (Sua Lógica)
    // ================================================================

    // 1. Define o "valor de dificuldade" (pedido por você)
    $difficulty_bonus = 0;
    if ($difficulty === 'medium') {
        $difficulty_bonus = 1;
    } elseif ($difficulty === 'hard') {
        $difficulty_bonus = 2;
    }
    
    // 2. Define o nível base da luta (Player + Dificuldade)
    $base_fight_level = $player_level + $difficulty_bonus;

    // 3. Aplica a variação (o rand(-1, 1) que você tinha)
    $level_diff_rng = rand(-1, 1);
    
    // 4. Calcula o nível final do monstro
    $monster_level = max(1, $base_fight_level + $level_diff_rng); // Nível mínimo 1
    
    // 5. Calcula o "gap" (diferença) do nível base DO MONSTRO (para escalar stats)
    $level_gap = $monster_level - $template['base_level'];

    // ================================================================
    
    // ... (verificações de 'stats_per_level' e 'base_exp') ...

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