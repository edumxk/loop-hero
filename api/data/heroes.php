<?php
/**
 * /api/data/heroes.php
 * Define os templates-base para criar novos heróis (Nível 1).
 */
return [
    'human_knight' => [
        'name'     => 'Cavaleiro Humano',
        'sprite_folder' => 'assets/heroes/paladino/',
        'base_stats' => [ // Atributos puros
            'max_hp'   => 120,
            'strength' => 7,
            'luck'     => 3,
            'speed' => 12,
        ],
        'combat_stats_base' => [ // Stats de combate (antes de 'strength' e 'luck')
            'attack'      => 6,
            'defense'     => 2,
            'crit_chance' => 0.05,
            'crit_mult'   => 1.5
        ],
        'starting_equipment' => [
            'weapon1' => 'sword', // ID do item (futuro)
            'weapon2' => 'shield',
        ]
    ],
    'dwarf_berserker' => [
        'name'     => 'Berserker Anão',
        'sprite_folder' => 'assets/heroes/anao/',
        'base_stats' => [
            'max_hp'   => 140,
            'strength' => 8,
            'luck'     => 2,
            'speed' => 10,
        ],
        'combat_stats_base' => [
            'attack'      => 8,
            'defense'     => 1,
            'crit_chance' => 0.03,
            'crit_mult'   => 1.75 // Mais dano crítico
        ],
        'starting_equipment' => [
            'weapon1' => 'greataxe',
            'weapon2' => null, // Arma de 2 mãos
        ]
    ],
    // Adicione os outros 3 heróis aqui (elfo negro, orc, elfo)
];