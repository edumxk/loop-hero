<?php
/**
 * /api/logic/battle_logic.php
 * Lógica de combate com Mitigação Percentual (Cap 75%).
 */

function calculateBattleDamage($attacker_stats, $defender_stats) {
    // 1. Extrair status
    $atk = $attacker_stats['attack'];
    $def = $defender_stats['defense']; // Agora 1 Def = 3% Resistência
    
    $crit_chance = $attacker_stats['crit_chance'] ?? 0.05;
    $crit_mult = $attacker_stats['crit_mult'] ?? 1.5;

    // ============================================================
    // 2. NOVA FÓRMULA DE DEFESA (PERCENTUAL)
    // ============================================================
    
    // Cada ponto de defesa = 3% (0.03)
    $reduction_percent = $def * 0.02;
    
    // Teto máximo de 75% (0.75)
    if ($reduction_percent > 0.85) {
        $reduction_percent = 0.85;
    }
    
    // Calcula o dano base reduzido
    // Ex: 100 Atk contra 10 Def (30%) -> 100 * (1 - 0.30) = 70 Dano
    $base_damage = $atk * (1 - $reduction_percent);

    // Dano Mínimo (Garante 1 de dano para não travar batalha)
    $final_damage = max(1, floor($base_damage));

    // 3. Variação Aleatória (RNG +/- 10%)
    $variance = rand(90, 110) / 100; 
    $final_damage = floor($final_damage * $variance);

    // 4. Crítico
    $is_crit = (rand(0, 100) / 100) <= $crit_chance;
    $log_msg = "";

    if ($is_crit) {
        $final_damage = floor($final_damage * $crit_mult);
        $log_msg = "CRÍTICO! (-$final_damage HP)";
    } else {
        // Mostra o dano e, se tiver defesa, mostra quanto mitigou (opcional, para debug visual)
        $percent_text = round($reduction_percent * 100);
        $log_msg = "Ataque (-$final_damage HP) [🛡️{$percent_text}%]";
    }

    return [
        'damage' => (int)$final_damage,
        'is_crit' => $is_crit,
        'log' => $log_msg
    ];
}
?>