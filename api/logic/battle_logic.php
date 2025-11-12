<?php
function calculateBattleDamage($attackerStats, $defenderStats) {
    $is_crit = (mt_rand(1, 1000) / 1000.0) <= $attackerStats['crit_chance'];
    $base_damage = $attackerStats['attack'];
    
    if ($is_crit) {
        $damage = floor($base_damage * $attackerStats['crit_mult']);
    } else {
        $damage = $base_damage;
    }

    $defense = $defenderStats['defense'];
    $final_damage = max(1, floor($damage - $defense));
    $log = $is_crit ? " (CRÍTICO!)" : "";

    return [
        'damage' => $final_damage,
        'is_crit' => $is_crit,
        'log' => "causou $final_damage de dano" . $log
    ];
}