<?php
/**
 * /api/data/monsters.php
 * Define os templates-base dos monstros.
 */

// NOTA: O 'sprite_url' foi corrigido de 'monstes' para 'monsters'
// e os caracteres invisíveis (NBSP) foram removidos.

return [
    'goblin' => [
        'name'          => 'Goblin Ladrão',
        'sprite_url'    => 'assets/monsters/goblin.png',
        'base_level'    => 1,
        'base_exp'      => 1, // EXP que ele dá no nível base
        'base_gold'     => 1, // <-- ADICIONADO
        'stats' => [ // Stats no Nível Base
            'max_hp'        => 80,
            'attack'        => 12,
            'defense'       => 4,
            'crit_chance'   => 0.1,
            'crit_mult'     => 1.5
        ],
        'stats_per_level' => [ // Bônus por nível acima do base
            'max_hp'        => 15,
            'attack'        => 2,
            'defense'       => 3,
        ]
    ],
    'orc' => [
        'name'          => 'Orc Brutamontes',
        'sprite_url'    => 'assets/monsters/orc.png',
        'base_level'    => 3, // Aparece a partir do Nível 3
        'base_exp'      => 3,
        'base_gold'     => 3, // <-- ADICIONADO
        'stats' => [
            'max_hp'        => 180,
            'attack'        => 18,
            'defense'       => 12,
            'crit_chance'   => 0.05,
            'crit_mult'     => 2.0
        ],
        'stats_per_level' => [
            'max_hp'        => 22,
            'attack'        => 4,
            'defense'       => 3,
        ]
        ],
    'troll' => [
        'name'          => 'Troll da Montanha',
        'sprite_url'    => 'assets/monsters/slime.png', 
        'base_level'    => 5, // Ele é forte
        'base_exp'      => 5,
        'base_gold'     => 10,
        'stats' => [
            'max_hp'        => 200,
            'attack'        => 18,
            'defense'       => 10,
            'crit_chance'   => 0.05,
            'crit_mult'     => 1.5
        ],
        'stats_per_level' => [
            'max_hp'        => 25,
            'attack'        => 3,
            'defense'       => 2,
        ]
    ]
];