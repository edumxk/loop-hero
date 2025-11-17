<?php
/**
 * /api/data/monsters.php
 * Define os templates-base dos monstros.
 */

return [
    'goblin' => [
        'name'          => 'Goblin Ladrão',
        'sprite_folder' => 'assets/monsters/goblin/',
        'base_level'    => 1,
        'base_exp'      => 1,
        'base_gold'     => 1,
        'stats' => [
            'max_hp'        => 80,
            'attack'        => 12,
            'defense'       => 4,
            'crit_chance'   => 0.1,
            'crit_mult'     => 1.5,
            'speed'         => 15,
            'potions'       => 1
        ],
        'stats_per_level' => [
            'max_hp'        => 15,
            'attack'        => 2,
            'defense'       => 3,
        ]
    ],
    'orc' => [
        'name'          => 'Orc Brutamontes',
        'sprite_folder' => 'assets/monsters/orc/',
        'base_level'    => 3,
        'base_exp'      => 3,
        'base_gold'     => 3,
        'stats' => [
            'max_hp'        => 180,
            'attack'        => 18,
            'defense'       => 12,
            'crit_chance'   => 0.05,
            'crit_mult'     => 2.0,
            'speed'         => 8,
            'potions'       => 2
        ],
        'stats_per_level' => [
            'max_hp'        => 22,
            'attack'        => 4,
            'defense'       => 3,
        ]
    ],
    'troll' => [
        'name'          => 'Troll da Montanha',
        'sprite_folder' => 'assets/monsters/troll/', 
        'base_level'    => 5,
        'base_exp'      => 5,
        'base_gold'     => 10,
        
        // CORREÇÃO AQUI: 'speed' e 'potions' foram movidos para DENTRO de 'stats'
        // para seguir o padrão dos outros monstros e evitar erro de lógica.
        'stats' => [
            'max_hp'        => 200,
            'attack'        => 18,
            'defense'       => 10,
            'crit_chance'   => 0.05,
            'crit_mult'     => 1.5,
            'speed'         => 10, // Mantido seu valor
            'potions'       => 3   // Mantido seu valor
        ],
        'stats_per_level' => [
            'max_hp'        => 25,
            'attack'        => 3,
            'defense'       => 2,
        ]
    ]
];