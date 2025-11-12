<?php
/**
 * /api/data/maps/map1.php
 * Define a estrutura e eventos do primeiro mapa (VERSÃO MAIOR 7x7)
 */
return [
    'id' => 'map1',
    'name' => 'Ruínas Assombradas',
    'start_pos' => ['x' => 3, 'y' => 6], // Posição 'S' (Centro inferior)

    // A representação visual da matriz (7x7)
    'map' => [
        [0, 0, 1, 1, 1, 0, 0], // Linha 0
        [0, 0, 1, 0, 1, 1, 0], // Linha 1
        [0, 0, 1, 0, 0, 1, 0], // Linha 2
        ['E', 1, 1, 0, 0, 1, 1], // Linha 3
        [0, 0, 0, 0, 0, 1, 0], // Linha 4
        [0, 1, 1, 1, 1, 1, 0], // Linha 5
        [0, 0, 0, 'S', 0, 0, 0], // Linha 6
    ],

    // Eventos (o que acontece em cada célula)
    'events' => [
        // Chave = "Y,X"
        '5,1' => ['type' => 'monster', 'difficulty' => 'easy'],
        '5,3' => ['type' => 'treasure', 'value' => 25],
        '5,5' => ['type' => 'monster', 'difficulty' => 'easy'],
        '3,0' => ['type' => 'trap', 'damage' => 15],
        '3,3' => ['type' => 'treasure', 'value' => 75], // Centro
        '3,6' => ['type' => 'monster', 'difficulty' => 'medium'],
        '1,1' => ['type' => 'monster', 'difficulty' => 'easy'],
        '1,3' => ['type' => 'trap', 'damage' => 10],
        '1,5' => ['type' => 'treasure', 'value' => 25],
        '3,1' => ['type' => 'monster', 'difficulty' => 'hard'], // Chefe?
    ]
];