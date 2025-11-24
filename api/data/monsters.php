<?php
return [
    'goblin' => [
        'name'          => 'Goblin Ladrão',
        'sprite_folder' => 'assets/monsters/goblin/',
        'base_level'    => 1,
        'base_exp'      => 37,
        'base_gold'     => 10,
        'scale'         => 0.7,
        
        'stats' => [
            'max_hp'        => 60,
            'attack'        => 8,
            'defense'       => 0,   // 0% Resistência inicial (Ele é fraco)
            'crit_chance'   => 0.1,
            'crit_mult'     => 1.5,
            'speed'         => 13,
            'potions'       => 1
        ],
        
        // CONTROLE DE ESCALA POR NÍVEL AQUI
        'stats_per_level' => [
            'max_hp'        => 15,  // Ganha +15 HP por nível
            'attack'        => 3,   // Ganha +2 Atk por nível
            'defense'       => 2, 
            'speed'         => 1.5    // Ganha +1 Velocidade por nível,
        ]
    ],

    'orc' => [
        'name'          => 'Orc Brutamontes',
        'sprite_folder' => 'assets/monsters/orc/',
        'base_level'    => 3,
        'base_exp'      => 102,
        'base_gold'     => 20,
        'scale'         => 1.3,
        
        'stats' => [
            'max_hp'        => 180,
            'attack'        => 15,
            'defense'       => 5,   // 3 Def = 9% Resistência inicial
            'crit_chance'   => 0.05,
            'crit_mult'     => 2.0,
            'speed'         => 8,
            'potions'       => 0
        ],
        
        'stats_per_level' => [
            'max_hp'        => 30,
            'attack'        => 5,
            'defense'       => 2,
            'speed'         => 1.5
        ]
    ],

    'troll' => [
        'name'          => 'Troll da Montanha',
        'sprite_folder' => 'assets/monsters/troll/',
        'base_level'    => 5,
        'base_exp'      => 212,
        'base_gold'     => 60,
        'scale'         => 2.0,
        
        'stats' => [
            'max_hp'        => 350,
            'attack'        => 25,
            'defense'       => 8,   // 5 Def = 15% Resistência inicial
            'crit_chance'   => 0.15,
            'crit_mult'     => 1.5,
            'speed'         => 6.5,
            'potions'       => 0
        ],
        
        'stats_per_level' => [
            'max_hp'        => 50,
            'attack'        => 5,
            'defense'       => 1.5, // Ganha +4.5% Resistência por nível
            'speed'         => 1.0
        ],
        'growth' => [
            'exp_multiplier' => 1.2 // Monstro cresce 20% mais rápido por nível

        ]
    ]
];