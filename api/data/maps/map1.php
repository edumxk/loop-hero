<?php

/**
 * /api/data/maps/map1.php
 * Define a estrutura e eventos do primeiro mapa (VERSÃO MAIOR 7x7)
 */
return [
    'id' => 'map1',
    'name' => 'Ruínas Assombradas',
    'start_pos' => ['x' => 3, 'y' => 14], // Posição 'S' (Centro inferior)

    // A representação visual da matriz (7x7)
    'map' => [
        ['E', 0, 1, 1, 1, 0, 1], // Linha 0
        [1, 0, 1, 0, 1, 1, 1], // Linha 1
        [1, 0, 1, 0, 0, 1, 0], // Linha 2
        [1, 1, 1, 0, 0, 1, 1], // Linha 3
        [0, 0, 0, 0, 0, 1, 0], // Linha 4
        [0, 1, 1, 1, 1, 1, 0], // Linha 5
        [0, 0, 0, 0, 0, 1, 0], // Linha 6
        [1, 1, 1, 1, 1, 1, 0], // Linha 7
        [1, 0, 0, 1, 0, 0, 0], // Linha 8
        [1, 0, 0, 1, 0, 0, 0], // Linha 9
        [0, 1, 1, 1, 1, 1, 0], // Linha 10
        [0, 1, 0, 0, 0, 1, 0], // Linha 11
        [0, 1, 0, 0, 0, 0, 0], // Linha 12
        [0, 1, 1, 1, 1, 1, 0], // Linha 13
        [0, 0, 0, 'S', 0, 0, 0], // Linha 14
    ],

    // Eventos (o que acontece em cada célula)
    'events' => [
        '13,4' => [
            'type' => 'monster',
            'difficulty' => 'easy',
            'monster_id' => 'goblin'
        ],
        '13,5' => [
            'type' => 'treasure',
            'treasure_type' => 'gold',
            'value' => 5
        ],
        '12,1' => [
            'type' => 'monster',
            'difficulty' => 'easy',
            'monster_id' => 'goblin'
        ],
        '10,4' => [
            'type' => 'monster',
            'difficulty' => 'easy',
            'monster_id' => 'goblin'
        ],
        '11,5' => [
            'type' => 'trap',
            'damage' => 5
        ],
        '13,5' => [
            'type' => 'treasure',
            'treasure_type' => 'potion',
            'value' => 2
        ],
         '9,0' => [
            'type' => 'treasure',
            'treasure_type' => 'potion',
            'value' => 5
        ],
        '5,1' => [
            'type' => 'monster',
            'difficulty' => 'medium',
            'monster_id' => 'goblin'
        ],
        '8,0' => [
            'type' => 'monster',
            'difficulty' => 'hard',
            'monster_id' => 'goblin'
        ],
        '7,0' => [
            'type' => 'monster',
            'difficulty' => 'hard',
            'monster_id' => 'goblin'
        ],
        '7,1' => [
            'type' => 'monster',
            'difficulty' => 'hard',
            'monster_id' => 'goblin'
        ],
        '7,2' => [
            'type' => 'monster',
            'difficulty' => 'hard',
            'monster_id' => 'troll'
        ],
        '5,3' => [
            'type' => 'treasure',
            'treasure_type' => 'gold',
            'value' => 5
        ],
        '5,5' => [
            'type' => 'monster',
            'difficulty' => 'hard',
            'monster_id' => 'goblin'
        ],
        '3,0' => [
            'type' => 'monster',
            'difficulty' => 'hard',
            'monster_id' => 'troll'
        ],
        '3,2' => [
            'type' => 'treasure',
            'treasure_type' => 'gold',
            'value' => 5
        ], // Centro
        '3,6' => [
            'type' => 'monster',
            'difficulty' => 'hard',
            'monster_id' => 'goblin'
        ],
        '1,2' => [
            'type' => 'monster',
            'difficulty' => 'hard',
            'monster_id' => 'goblin'
        ],
        '1,5' => [
            'type' => 'monster',
            'difficulty' => 'medium',
            'monster_id' => 'orc'
        ],
        '0,3' => [
            'type' => 'trap',
            'damage' => 5
        ],
        '1,5' => [
            'type' => 'treasure',
            'treasure_type' => 'gold',
            'value' => 5
        ],
        '3,1' => [
            'type' => 'monster',
            'difficulty' => 'hard',
            'monster_id' => 'orc'
        ],
        '0,6' => [
            'type' => 'monster',
            'difficulty' => 'hard',
            'monster_id' => 'orc'
        ],
    ]
];
