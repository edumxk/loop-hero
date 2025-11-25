<?php

/**
 * /api/data/maps/map1.php
 * Define a estrutura e eventos do primeiro mapa (VERSÃO MAIOR 7x7)
 */
return [
    'id' => 'map2',
    'name' => 'A Floresta Eterna Sombria',
    'start_pos' => ['x' => 4, 'y' => 14], // Posição 'S' (Centro inferior)

    // A representação visual da matriz (7x7)
    'map' => [
        ['E',0,1, 1, 1, 1, 1, 0, 0, 1], // Linha 0
        [1, 0, 0, 1, 0, 0, 1, 1, 1, 1], // Linha 1
        [1, 1, 1, 1, 0, 0, 1, 0, 0, 0], // Linha 2
        [0, 0, 0, 0, 1, 1, 1, 1, 0, 0], // Linha 3
        [0, 0, 0, 0, 1, 0, 0, 1, 0, 0], // Linha 4
        [1, 1, 1, 1, 1, 0, 0, 1, 0, 0], // Linha 5
        [1, 0, 0, 0, 1, 0, 0, 1, 0, 1], // Linha 6
        [1, 0, 0, 0, 1, 1, 1, 1, 1, 1], // Linha 7
        [1, 0, 0, 0, 0, 0, 0, 0, 1, 0], // Linha 8
        [0, 1, 1, 1, 1, 1, 1, 1, 1, 0], // Linha 9
        [0, 1, 0, 0, 0, 0, 0, 0, 0, 0], // Linha 10
        [0, 1, 0, 0, 0, 0, 0, 0, 0, 0], // Linha 11
        [0, 1, 1, 1, 1, 1, 1, 1, 1, 0], // Linha 12
        [0, 0, 0, 0, 1, 0, 0, 0, 1, 0], // Linha 13
        [0, 0, 0, 0,'S',0, 0, 0, 0, 0], // Linha 14
    ],

    // Eventos (o que acontece em cada célula)
    'events' => [
        '12,2' => [
            'type' => 'monster',
            'difficulty' => 'easy',
            'monster_id' => 'goblin'
        ],
        '12,6' => [
            'type' => 'monster',
            'difficulty' => 'easy',
            'monster_id' => 'goblin'
        ],
        '12,7' => [
            'type' => 'treasure',
            'treasure_type' => 'gold',
            'value' => 75,
        ],
        '12,8' => [
            'type' => 'treasure',
            'treasure_type' => 'gold',
            'value' => 50,
        ]
        ,
        '13,8' => [
            'type' => 'monster',
            'difficulty' => 'medium',
            'monster_id' => 'goblin'
        ],
        '9,1' => [
            'type' => 'monster',
            'difficulty' => 'easy',
            'monster_id' => 'goblin'
        ],
         '9,3' => [
            'type' => 'treasure',
            'treasure_type' => 'potion',
            'value' => 3
        ],
        '9,6' => [
            'type' => 'monster',
            'difficulty' => 'easy',
            'monster_id' => 'orc'
        ],
        '6,9' => [
            'type' => 'shop',
        ],
        '7,5' => [
            'type' => 'monster',
            'difficulty' => 'medium',
            'monster_id' => 'goblin'
        ],
        '5,4' => [
            'type' => 'monster',
            'difficulty' => 'medium',
            'monster_id' => 'goblin'
        ],
        '5,2' => [
            'type' => 'monster',
            'difficulty' => 'medium',
            'monster_id' => 'orc',
            'potions' => 1
            
        ],
        '5,1' => [
            'type' => 'monster',
            'difficulty' => 'hard',
            'monster_id' => 'goblin'
        ],
        '5,0' => [
            'type' => 'monster',
            'difficulty' => 'hard',
            'monster_id' => 'goblin'
        ],
        '6,0' => [
            'type' => 'monster',
            'difficulty' => 'hard',
            'monster_id' => 'orc'
        ],
        '7,0' => [
            'type' => 'monster',
            'difficulty' => 'medium',
            'monster_id' => 'troll'
        ],
        '8,0' => [
            'type' => 'treasure',
            'treasure_type' => 'gold',
            'value' => 750
        ],
        '5,7' => [
            'type' => 'monster',
            'difficulty' => 'hard',
            'monster_id' => 'goblin'
        ],
         '3,6' => [
            'type' => 'monster',
            'difficulty' => 'medium',
            'monster_id' => 'orc'
        ],
        '1,7' => [
            'type' => 'monster',
            'difficulty' => 'medium',
            'monster_id' => 'orc',
            'potions' => 1
        ],
        '3,4' => [
            'type' => 'trap',
            'damage' => 30
        ],
        '0,9' => [
            'type' => 'treasure',
            'treasure_type' => 'gold',
            'value' => 100
        ],
        '0,2' => [
            'type' => 'shop'
        ],
        '3,6' => [
            'type' => 'monster',
            'difficulty' => 'hard',
            'monster_id' => 'goblin'
        ],
        '1,3' => [
            'type' => 'monster',
            'difficulty' => 'hard',
            'monster_id' => 'goblin'
        ],
        '2,3' => [
            'type' => 'monster',
            'difficulty' => 'medium',
            'monster_id' => 'orc',
            'potions' => 1
            
        ],
        '2,2' => [
            'type' => 'monster',
            'difficulty' => 'hard',
            'monster_id' => 'orc',
            'potions' => 1
        ],
        '2,1' => [
            'type' => 'treasure',
            'treasure_type' => 'potion',
            'value' => 5
        ],
        '2,0' => [
            'type' => 'monster',
            'difficulty' => 'hard',
            'monster_id' => 'orc',
            'potions' => 2
        ],
        '1,0' => [
            'type' => 'monster',
            'difficulty' => 'hard',
            'monster_id' => 'troll', //boss final
            'potions' => 3
        ],
    ]
];
