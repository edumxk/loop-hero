<?php
session_start();
header('Content-Type: application/json');

// --- CARREGAR RECURSOS ---
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/logic/battle_logic.php';
require_once __DIR__ . '/logic/player_logic.php';
require_once __DIR__ . '/logic/monster_logic.php';
require_once __DIR__ . '/logic/map_logic.php';

$pdo = getDbConnection();
$action = $_GET['action'] ?? 'default';
$postData = json_decode(file_get_contents('php://input'), true) ?? [];

// ============================================================================
// ROTEADOR DE AÇÕES
// ============================================================================

// 1. VERIFICAR SAVES
if ($action === 'check_saves') {
    $stmt = $pdo->query("SELECT hero_id_key, class_name, level FROM heroes ORDER BY level DESC");
    echo json_encode($stmt->fetchAll());
    exit;
}

// 2. INICIAR / CARREGAR JOGO (MAPA)
if ($action === 'load_game' || $action === 'start') {
    try {
        $hero_id = $postData['hero_id'] ?? 'human_knight';
        
        if ($action === 'start') {
            $stmt = $pdo->prepare("DELETE FROM heroes WHERE hero_id_key = ?");
            $stmt->execute([$hero_id]);
            $player = createNewPlayer($pdo, $hero_id);
        } else {
            $player = getPlayerState($pdo, $hero_id);
        }
        
        // Limpa qualquer batalha antiga presa na sessão
        unset($_SESSION['battle_state']);

        $map = loadMapData($player['current_map_id']);
        
        echo json_encode([
            'view' => 'map',
            'player' => $player,
            'map_data' => $map
        ]);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// 3. MOVER NO MAPA
if ($action === 'move') {
    try {
        $direction = $postData['direction'] ?? null;
        if (!$direction) throw new Exception('Direção não fornecida.');

        $hero_id = $postData['hero_id'];
        $player = getPlayerState($pdo, $hero_id);

        $result = processMove($player, $direction);
        $player = $result['player'];
        
        // Salva o estado (HP, Posição, Ouro, Eventos Concluídos)
        savePlayerState($pdo, $player);

        $response = [
            'view' => 'map',
            'status' => $result['status'],
            'log' => $result['log'],
            'player_pos' => [
                'x' => $player['current_map_pos_x'],
                'y' => $player['current_map_pos_y']
            ],
            'hud_update' => [
                'hp' => $player['hp'],
                'max_hp' => $player['base_stats']['max_hp'],
                'gold' => $player['gold'],
                'potions' => $player['potions']
            ]
        ];
        
        if (isset($result['event'])) {
            $response['event'] = $result['event'];
        }
        
        echo json_encode($response);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// 4. INICIAR BATALHA (ATUALIZADO)
if ($action === 'trigger_battle') {
    try {
        $player = getPlayerState($pdo, $postData['hero_id']);
        $monster = spawnMonster($player['level'], $postData['difficulty'] ?? 'easy', $postData['monster_id'] ?? null);

        // Garante atributos padrão
        if (!isset($player['base_stats']['speed'])) $player['base_stats']['speed'] = 10;
        if (!isset($monster['stats']['speed'])) $monster['stats']['speed'] = 8;
        if (!isset($monster['stats']['potions'])) $monster['stats']['potions'] = 0;

        $_SESSION['battle_state'] = [
            'player' => $player,
            'monster' => $monster,
            'meters' => ['player' => 0, 'monster' => 0],
            'game_status' => ['game_over' => false, 'log' => "Um(a) {$monster['name']} apareceu!"],
            'turn_flags' => ['player_hit' => false, 'monster_hit' => false],
            
            // --- MECÂNICA DE DEFESA ---
            'defense_stacks' => ['player' => 0, 'monster' => 0], // Hits restantes
            'defense_cooldown' => ['player' => 0, 'monster' => 0] // Turnos para recarregar
        ];
        
        echo json_encode(['view' => 'battle', 'battle_data' => packageStateForFrontend($_SESSION['battle_state'])]); exit;
    } catch (Throwable $e) { http_response_code(500); echo json_encode(['error' => $e->getMessage()]); exit; }
}

// ============================================================================
// 6. SISTEMA DE EVOLUÇÃO (DISTRIBUIÇÃO DE PONTOS)
// ============================================================================

if ($action === 'distribute_point') {
    try {
        $hero_id = $postData['hero_id'] ?? null;
        $attribute = $postData['attribute'] ?? null; // 'strength', 'agility', 'luck', 'vitality'
        
        if (!$hero_id || !$attribute) throw new Exception("Dados inválidos.");

        $player = getPlayerState($pdo, $hero_id);

        // 1. Validação
        if ($player['attribute_points'] <= 0) {
            echo json_encode(['error' => 'Sem pontos de atributo disponíveis!']);
            exit;
        }

        // 2. Aplicação do Ponto
        switch ($attribute) {
            case 'strength':
                $player['base_stats']['strength']++;
                break;
            case 'agility': // No DB usamos 'speed' como base para agilidade
                $player['base_stats']['agility']++;
                break;
            case 'luck':
                $player['base_stats']['luck']++;
                break;
            case 'vitality':
                $player['base_stats']['max_hp'] += 25; // 1 Vit = 10 HP Base
                $player['hp'] += 25; // Cura o valor ganho
                break;
            default:
                throw new Exception("Atributo desconhecido: $attribute");
        }

        // 3. Consome o ponto
        $player['attribute_points']--;

        // 4. RECALCULA STATUS DE COMBATE (Ataque, Defesa, Crit)
        // Essa função (definida em player_logic.php) atualiza 'combat_stats' baseado nos novos 'base_stats'
        $player = recalculate_player_stats($player);

        // 5. Salva e Retorna
        savePlayerState($pdo, $player);

        echo json_encode([
            'view' => 'character_update', // Tag para o JS saber que é só update de modal
            'player' => $player,
            'log' => "Atributo $attribute melhorado!"
        ]);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// ============================================================================
// SISTEMA DE BATALHA (ATB)
// ============================================================================

if (!isset($_SESSION['battle_state'])) { echo json_encode(['error' => 'Nenhuma batalha.']); exit; }

$state = &$_SESSION['battle_state'];
$player = &$state['player'];
$monster = &$state['monster'];

if ($state['game_status']['game_over']) {
    echo json_encode(['view' => 'battle_over', 'battle_data' => packageStateForFrontend($state), 'log' => $state['game_status']['log'], 'hero_id' => ($player['hp'] > 0 ? $player['hero_id_key'] : null)]); exit;
}

// --- TICK (MONSTRO) ---
if ($action === 'tick') {
    $log = [];
    $something_happened = false;
    $multiplier = (int)($postData['multiplier'] ?? 1);
    if (!in_array($multiplier, [1, 2, 4, 8])) $multiplier = 1;

    $state['turn_flags']['player_hit'] = false;
    $state['turn_flags']['monster_hit'] = false;

    // Enche barras
    if ($state['meters']['player'] < 100) $state['meters']['player'] = min(100, $state['meters']['player'] + ($player['base_stats']['speed'] * $multiplier));
    if ($state['meters']['monster'] < 100) $state['meters']['monster'] = min(100, $state['meters']['monster'] + ($monster['stats']['speed'] * $multiplier));

    // IA DO MONSTRO
    if ($state['meters']['monster'] >= 100) {
        $something_happened = true;
        $state['meters']['monster'] = 0; 

        // Decrementa Cooldown do Monstro se ele agir
        if ($state['defense_cooldown']['monster'] > 0) {
            $state['defense_cooldown']['monster']--;
        }

        $monster_hp_percent = ($monster['hp'] / $monster['stats']['max_hp']) * 100;
        
        // 1. USAR DEFESA (HP < 20% e SEM Cooldown)
        if ($monster_hp_percent < 20 && $state['defense_cooldown']['monster'] == 0) {
            $state['defense_stacks']['monster'] = 3; // 3 Hits
            $state['defense_cooldown']['monster'] = 5; // 5 Turnos de espera
            $log[] = "O {$monster['name']} entrou em DEFESA! (🛡️ Ativado)";
        }
        // 2. CURA
        else if ($monster_hp_percent < 50 && $monster['stats']['potions'] > 0) {
            $monster['stats']['potions']--;
            $heal = floor($monster['stats']['max_hp'] * 0.3);
            $monster['hp'] = min($monster['stats']['max_hp'], $monster['hp'] + $heal);
            $log[] = "O {$monster['name']} curou +$heal HP!";
        } 
        // 3. ATAQUE (Consome Stacks do Jogador)
        else {
            $player_temp_stats = $player['combat_stats'];
            
            // Verifica se o JOGADOR tem defesa ativa
            if ($state['defense_stacks']['player'] > 0) {
                $player_temp_stats['defense'] += 10; // Aplica +10 Def
                $state['defense_stacks']['player']--; // CONSOME 1 STACK DO JOGADOR
                // $log[] = "(Sua defesa reduziu o impacto!)";
            }

            $result = calculateBattleDamage($monster['stats'], $player_temp_stats);
            $dmg = $result['damage'];
            $player['hp'] -= $dmg;
            $state['turn_flags']['player_hit'] = true;
            $log[] = "{$monster['name']} atacou ($dmg)!";
        }

        if ($player['hp'] <= 0) {
            $player['hp'] = 0; $state['game_status']['game_over'] = true;
            $log[] = "Derrota! Seu herói caiu.";
            $stmt = $pdo->prepare("DELETE FROM heroes WHERE id = ?"); $stmt->execute([$player['id']]);
            $state['game_status']['log'] = implode(' ', $log); unset($_SESSION['battle_state']);
            echo json_encode(['view' => 'battle_over', 'log' => implode(' ', $log), 'battle_data' => packageStateForFrontend($state), 'hero_id' => null]); exit;
        }
    }

    if ($something_happened) $state['game_status']['log'] = implode(' ', $log);
    echo json_encode(['view' => 'battle', 'battle_data' => packageStateForFrontend($state)]); exit;
}

// --- AÇÕES JOGADOR ---
if ($action === 'attack' || $action === 'defend' || $action === 'potion') {
    if ($state['meters']['player'] < 100) { echo json_encode(['error' => 'Aguarde!']); exit; }

    $log = [];
    $state['turn_flags']['player_hit'] = false;
    $state['turn_flags']['monster_hit'] = false;

    // Decrementa Cooldown do Jogador ao agir (se > 0)
    if ($state['defense_cooldown']['player'] > 0) {
        $state['defense_cooldown']['player']--;
    }

    switch ($action) {
        case 'attack':
            $monster_temp_stats = $monster['stats'];
            
            // Verifica se o MONSTRO tem defesa ativa
            if ($state['defense_stacks']['monster'] > 0) {
                $monster_temp_stats['defense'] += 10; // Aplica +10 Def
                $state['defense_stacks']['monster']--; // CONSOME 1 STACK DO MONSTRO
                $log[] = "[Defesa do Inimigo ativa!]";
            }

            $result = calculateBattleDamage($player['combat_stats'], $monster_temp_stats);
            $monster['hp'] -= $result['damage'];
            $state['turn_flags']['monster_hit'] = true;
            $log[] = "Você atacou: " . $result['log'];
            $state['meters']['player'] = 0;
            break;
            
        case 'defend':
            // Verifica Cooldown antes de permitir
            if ($state['defense_cooldown']['player'] > 0) {
                // Como decrementamos acima, somamos 1 de volta para não gastar turno "à toa" se o front falhar
                // Mas idealmente o botão está desabilitado no front.
                $state['defense_cooldown']['player']++; 
                $log[] = "Habilidade em recarga! ({$state['defense_cooldown']['player']} turnos)";
                // Não zera a barra do jogador, permite tentar outra coisa
            } else {
                $state['defense_stacks']['player'] = 3;
                $state['defense_cooldown']['player'] = 5; // Define 5 turnos de cooldown
                $log[] = "GUARDA LEVANTADA! (+10 Def por 3 hits)";
                $state['meters']['player'] = 0; // Zera a barra
            }
            break;
            
        case 'potion':
            if ($player['potions'] > 0) {
                $player['potions']--;
                $heal = floor($player['base_stats']['max_hp'] * 0.4); 
                $player['hp'] = min($player['base_stats']['max_hp'], $player['hp'] + $heal);
                $log[] = "Você curou $heal HP.";
                $state['meters']['player'] = 0;
            } else {
                $log[] = "Sem poções!";
            }
            break;
    }

    // Vitória / Morte Monstro
    if ($monster['hp'] <= 0) {
        $monster['hp'] = 0; $state['game_status']['game_over'] = true;
        $exp_gained = $monster['exp_reward']; $player['exp'] += $exp_gained;
        $gold_gained = $monster['gold_reward']; $player['gold'] += $gold_gained;
        $final_log = ["Vitória!", "Ganhou $exp_gained EXP e $gold_gained Ouro."];
        
        $levelup = checkLevelUp($player); $player = $levelup['player'];
        if($levelup['log']) $final_log[] = $levelup['log'];

        $player['completed_events'][$player['current_map_id']]["{$player['current_map_pos_y']},{$player['current_map_pos_x']}"] = true;
        savePlayerState($pdo, $player);

        $state['game_status']['log'] = implode(' ', $final_log); unset($_SESSION['battle_state']);
        echo json_encode(['view' => 'battle_over', 'log' => implode(' ', $final_log), 'battle_data' => packageStateForFrontend($state), 'hero_id' => $player['hero_id_key']]); exit;
    }

    $state['game_status']['log'] = implode(' ', $log);
    echo json_encode(['view' => 'battle', 'battle_data' => packageStateForFrontend($state)]); exit;
}

function packageStateForFrontend($state) {
    $all_heroes = require __DIR__ . '/data/heroes.php';
    $player = $state['player'];
    $monster = $state['monster'];
    $flat = $state['game_status'];
    $flat['player_hit'] = $state['turn_flags']['player_hit'];
    $flat['monster_hit'] = $state['turn_flags']['monster_hit'];
    
    // Dados de Defesa e Cooldown
    $flat['player_def_stacks'] = $state['defense_stacks']['player'] ?? 0;
    $flat['monster_def_stacks'] = $state['defense_stacks']['monster'] ?? 0;
    $flat['player_def_cd'] = $state['defense_cooldown']['player'] ?? 0; // Envia cooldown para o front
    
    $flat['meters'] = $state['meters'];
    $flat['player_name'] = ($player['class_name'] ?? '?') . " (Lvl " . ($player['level'] ?? '?') . ")";
    $flat['player_hp'] = $player['hp'];
    $flat['player_max_hp'] = $player['base_stats']['max_hp'];
    $flat['player_hp_percent'] = ($flat['player_max_hp'] > 0) ? ($player['hp'] / $flat['player_max_hp']) * 100 : 0;
    $flat['player_sprite_folder'] = $all_heroes[$player['hero_id_key']]['sprite_folder'] ?? '';
    $flat['player_scale'] = $all_heroes[$player['hero_id_key']]['scale'] ?? 1.0;
    $flat['potions'] = $player['potions'];
    $flat['player_stats'] = [
        'attack' => $player['combat_stats']['attack'],
        'defense' => $player['combat_stats']['defense'],
        'defense_display' => $player['combat_stats']['defense'] + ($flat['player_def_stacks'] > 0 ? 10 : 0),
        'crit_chance' => ($player['combat_stats']['crit_chance'] * 100)
    ];
    $flat['monster_name'] = $monster['name'];
    $flat['monster_hp'] = $monster['hp'];
    $flat['monster_max_hp'] = $monster['stats']['max_hp'];
    $flat['monster_hp_percent'] = ($flat['monster_max_hp'] > 0) ? ($monster['hp'] / $flat['monster_max_hp']) * 100 : 0;
    $flat['monster_sprite_folder'] = $monster['sprite_folder'];
    $flat['monster_scale'] = $monster['scale'] ?? 1.0;
    $flat['monster_stats'] = [
        'attack' => $monster['stats']['attack'],
        'defense' => $monster['stats']['defense'],
        'defense_display' => $monster['stats']['defense'] + ($flat['monster_def_stacks'] > 0 ? 10 : 0),
        'crit_chance' => ($monster['stats']['crit_chance'] * 100)
    ];
    return $flat;
}


?>