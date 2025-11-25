<?php
/**
 * /api/data/heroes.php
 * Define os templates-base para criar novos heróis (Nível 1).
 */
return [
    'human_knight' => [
        'name'     => 'Cavaleiro Humano',
        'sprite_folder' => 'assets/heroes/paladino/',
        'scale' => 1.0,
        'base_stats' => [ // Atributos puros
            'max_hp'   => 140,
            'strength' => 3,
            'luck'     => 1,
            'agility' => 1,
            'speed' => 10,
        ],
        'combat_stats_base' => [ // Stats de combate (antes de 'strength' e 'luck')
            'attack'      => 5,
            'defense'     => 2,
            'crit_chance' => 0.12,
            'crit_mult'   => 1.5
        ],
        'growth' => [
            'attack' => 2,  // Ganha 1.5 atk por nível
            'defense' => 2, // Ganha 1 def por nível
            'max_hp' => 15    // Usado se quiser calcular HP dinâmico
        ],
        'starting_equipment' => [
            'weapon1' => 'sword', // ID do item (futuro)
            'weapon2' => 'shield',
        ]
    ],
    'dwarf_berserker' => [
        'name'     => 'Berserker Anão',
        'sprite_folder' => 'assets/heroes/anao/',
        'scale' => 0.80,
        'base_stats' => [
            'max_hp'   => 140,
            'strength' => 2,
            'luck'     => 1,
            'agility' => 2,
            'speed' => 10,
        ],
        'combat_stats_base' => [
            'attack'      => 5,
            'defense'     => 2,
            'crit_chance' => 0.07,
            'crit_mult'   => 2 // Mais dano crítico
        ],
        'growth' => [
            'attack' => 3,  // Ganha muito ataque por nível
            'defense' => 1, // Ganha pouca defesa
            'max_hp' => 10
        ],
        'starting_equipment' => [
            'weapon1' => 'greataxe',
            'weapon2' => null, // Arma de 2 mãos
        ]
    ],
    // Adicione os outros 3 heróis aqui (elfo negro, orc, elfo)
];