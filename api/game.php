<?php
session_start();
header('Content-Type: application/json');

// Carrega os módulos
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/logic/battle_logic.php';
require_once __DIR__ . '/logic/player_logic.php';
require_once __DIR__ . '/logic/monster_logic.php';
require_once __DIR__ . '/logic/map_logic.php';

$pdo = getDbConnection();
$action = $_GET['action'] ?? 'default';
$postData = json_decode(file_get_contents('php://input'), true) ?? [];

// ============================================================================
// 0. AUTENTICAÇÃO (LOGIN / REGISTER) - Única parte pública
// ============================================================================
if ($action === 'login' || $action === 'register') {
    $cpf = preg_replace('/[^0-9]/', '', $postData['cpf'] ?? '');
    $password = $postData['password'] ?? '';
    $name = $postData['name'] ?? 'Viajante';

    if (strlen($cpf) < 1 || strlen($password) !== 4) {
        echo json_encode(['error' => 'CPF inválido ou Senha deve ter 4 dígitos.']);
        exit;
    }

    if ($action === 'register') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE cpf = ?");
        $stmt->execute([$cpf]);
        if ($stmt->fetch()) {
            echo json_encode(['error' => 'CPF já cadastrado.']); exit;
        }
        $stmt = $pdo->prepare("INSERT INTO users (name, cpf, password) VALUES (?, ?, ?)");
        $stmt->execute([$name, $cpf, $password]);
        $_SESSION['user_id'] = $pdo->lastInsertId();
        $_SESSION['user_name'] = $name;
    } 
    
    if ($action === 'login') {
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE cpf = ? AND password = ?");
        $stmt->execute([$cpf, $password]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
        } else {
            echo json_encode(['error' => 'CPF ou Senha incorretos.']); exit;
        }
    }

    echo json_encode(['success' => true, 'view' => 'title_screen', 'user_name' => $_SESSION['user_name']]);
    exit;
}

// ============================================================================
// 1. VERIFICAÇÃO DE SESSÃO (OBRIGATÓRIO DAQUI PRA BAIXO)
// ============================================================================
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

$user_id = $_SESSION['user_id']; // <--- VARIÁVEL GLOBAL DO USUÁRIO ATUAL

// ============================================================================
// 2. MENU E SAVES
// ============================================================================

if ($action === 'check_saves') {
    // CORREÇÃO: Busca apenas saves deste usuário
    $stmt = $pdo->prepare("SELECT hero_id_key, class_name, level FROM heroes WHERE user_id = ? ORDER BY level DESC");
    $stmt->execute([$user_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($action === 'load_game' || $action === 'start') {
    $hero_id = $postData['hero_id'] ?? 'human_knight';

    if ($action === 'start') {
        // CORREÇÃO: Deleta save antigo APENAS deste usuário
        $stmt = $pdo->prepare("DELETE FROM heroes WHERE hero_id_key = ? AND user_id = ?");
        $stmt->execute([$hero_id, $user_id]);
        
        // CORREÇÃO: Cria passando user_id
        $player = createNewPlayer($pdo, $hero_id, $user_id); 
    } else {
        // CORREÇÃO: Carrega passando user_id
        $player = getPlayerState($pdo, $hero_id, $user_id); 
    }

    unset($_SESSION['battle_state']);
    $map = loadMapData($player['current_map_id']);
    
    // Pega pasta do sprite para o front
    $all_heroes = require __DIR__ . '/data/heroes.php';
    $player['sprite_folder'] = $all_heroes[$player['hero_id_key']]['sprite_folder'] ?? '';

    echo json_encode([
        'view' => 'map', 
        'player' => $player, 
        'map_data' => $map
    ]);
    exit;
}

// ============================================================================
// 3. MOVIMENTO NO MAPA
// ============================================================================

if ($action === 'move') {
    $hero_id = $postData['hero_id'];
    
    // CORREÇÃO: Busca herói do usuário logado
    $player = getPlayerState($pdo, $hero_id, $user_id); 
    
    $result = processMove($player, $postData['direction'] ?? null);
    
    if (isset($result['player'])) {
        $player = $result['player'];
    }

    savePlayerState($pdo, $player); 
    
    $resp = [
        'view' => 'map',
        'status' => $result['status'],
        'log' => $result['log'],
        'player' => $player,
        'player_pos' => ['x' => $player['current_map_pos_x'], 'y' => $player['current_map_pos_y']],
        'hud_update' => [
            'hp' => $player['hp'],
            'max_hp' => $player['base_stats']['max_hp'],
            'gold' => $player['gold'],
            'potions' => $player['potions']
        ]
    ];

    if ($result['status'] === 'map_switch') {
        $resp['map_data'] = $result['new_map_data'];
    }
    if(isset($result['event'])) $resp['event'] = $result['event'];
    
    echo json_encode($resp);
    exit;
}

// ============================================================================
// 4. BATALHA (TRIGGER)
// ============================================================================

if ($action === 'trigger_battle') {
    try {
        $hero_id = $postData['hero_id'] ?? null;
        
        // CORREÇÃO: Busca herói do usuário logado
        $player = getPlayerState($pdo, $hero_id, $user_id);
        
        $difficulty = $postData['difficulty'] ?? 'easy';
        $monster_id = $postData['monster_id'] ?? null;
        $monster = spawnMonster($player['level'], $difficulty, $monster_id);

        if (!isset($player['base_stats']['speed'])) $player['base_stats']['speed'] = 10;
        if (!isset($monster['stats']['speed'])) $monster['stats']['speed'] = 8;
        if (!isset($monster['stats']['potions'])) $monster['stats']['potions'] = 0;

        $_SESSION['battle_state'] = [
            'player' => $player,
            'monster' => $monster,
            'meters' => ['player' => 0, 'monster' => 0],
            'game_status' => ['game_over' => false, 'log' => "Um(a) {$monster['name']} apareceu!"],
            'turn_flags' => ['player_hit' => false, 'monster_hit' => false, 'monster_healed' => false],
            'defense_stacks' => ['player' => 0, 'monster' => 0],
            'defense_cooldown' => ['player' => 0, 'monster' => 0]
        ];
        
        echo json_encode([
            'view' => 'battle', 
            'battle_data' => packageStateForFrontend($_SESSION['battle_state'])
        ]);
        exit;

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// ============================================================================
// 5. SISTEMA DE BATALHA (LOOP)
// ============================================================================

if (!isset($_SESSION['battle_state']) && !in_array($action, ['distribute_point', 'buy_item', 'get_shop_data'])) {
    // Se não é loja nem personagem e não tem batalha, ignora (exceto se for uma das actions acima)
}

if (isset($_SESSION['battle_state'])) {
    $state = &$_SESSION['battle_state'];
    $player = &$state['player'];
    $monster = &$state['monster'];

    if ($state['game_status']['game_over']) {
        echo json_encode(['view' => 'battle_over', 'battle_data' => packageStateForFrontend($state), 'log' => $state['game_status']['log'], 'hero_id' => ($player['hp'] > 0 ? $player['hero_id_key'] : null)]); exit;
    }

    if ($action === 'tick') {
        // ... (Lógica do Tick - MANTIDA IGUAL, pois usa $_SESSION) ...
        // ... Copie o conteúdo da sua ação tick aqui ou use o arquivo anterior ...
        // Vou resumir para focar na correção dos saves:
        $multiplier = (int)($postData['multiplier'] ?? 1);
        if (!in_array($multiplier, [1, 2, 4, 8])) $multiplier = 1;
        $state['turn_flags']['player_hit'] = false;
        $state['turn_flags']['monster_hit'] = false;
        $state['turn_flags']['monster_healed'] = false;

        // Enche barras
        if ($state['meters']['player'] < 100) $state['meters']['player'] = min(100, $state['meters']['player'] + ($player['base_stats']['speed'] * $multiplier));
        if ($state['meters']['monster'] < 100) $state['meters']['monster'] = min(100, $state['meters']['monster'] + ($monster['stats']['speed'] * $multiplier));

        // IA Monstro
        if ($state['meters']['monster'] >= 100) {
            // ... (Lógica IA Monstro igual ao anterior) ...
            $something_happened = true;
            $state['meters']['monster'] = 0; 
            if ($state['defense_cooldown']['monster'] > 0) $state['defense_cooldown']['monster']--;

            $monster_hp_percent = ($monster['hp'] / $monster['stats']['max_hp']) * 100;
            if ($monster_hp_percent < 20 && $state['defense_cooldown']['monster'] == 0) {
                $state['defense_stacks']['monster'] = 3; $state['defense_cooldown']['monster'] = 5;
            } else if ($monster_hp_percent < 50 && $monster['stats']['potions'] > 0) {
                $monster['stats']['potions']--;
                $heal = floor($monster['stats']['max_hp'] * 0.3);
                $monster['hp'] = min($monster['stats']['max_hp'], $monster['hp'] + $heal);
                $state['turn_flags']['monster_healed'] = true;
            } else {
                $player_temp_stats = $player['combat_stats'];
                if ($state['defense_stacks']['player'] > 0) {
                    $player_temp_stats['defense'] += 10; $state['defense_stacks']['player']--;
                }
                $result = calculateBattleDamage($monster['stats'], $player_temp_stats);
                $player['hp'] -= $result['damage'];
                $state['turn_flags']['player_hit'] = true;
            }

            if ($player['hp'] <= 0) {
                $player['hp'] = 0; $state['game_status']['game_over'] = true;
                $stmt = $pdo->prepare("DELETE FROM heroes WHERE id = ?"); // Deleta pelo ID único
                $stmt->execute([$player['id']]);
                unset($_SESSION['battle_state']);
                echo json_encode(['view' => 'battle_over', 'log' => "Derrota!", 'battle_data' => packageStateForFrontend($state), 'hero_id' => null]); exit;
            }
        }
        echo json_encode(['view' => 'battle', 'battle_data' => packageStateForFrontend($state)]); exit;
    }

    if ($action === 'attack' || $action === 'defend' || $action === 'potion') {
        // ... (Lógica Ações Player - MANTIDA IGUAL, usa $_SESSION) ...
        // ... Copie o conteúdo das ações attack/defend/potion aqui ...
        if ($state['meters']['player'] < 100) { echo json_encode(['error' => 'Aguarde!']); exit; }
        $state['turn_flags']['player_hit'] = false;
        $state['turn_flags']['monster_hit'] = false;
        if ($state['defense_cooldown']['player'] > 0) $state['defense_cooldown']['player']--;

        if ($action === 'attack') {
            $monster_temp_stats = $monster['stats'];
            if ($state['defense_stacks']['monster'] > 0) {
                $monster_temp_stats['defense'] += 10; $state['defense_stacks']['monster']--;
            }
            $result = calculateBattleDamage($player['combat_stats'], $monster_temp_stats);
            $monster['hp'] -= $result['damage'];
            $state['turn_flags']['monster_hit'] = true;
            $state['meters']['player'] = 0;
        }
        else if ($action === 'defend') {
            if ($state['defense_cooldown']['player'] > 0) { /* cd */ } 
            else { $state['defense_stacks']['player'] = 3; $state['defense_cooldown']['player'] = 5; $state['meters']['player'] = 0; }
        }
        else if ($action === 'potion') {
            if ($player['potions'] > 0) {
                $player['potions']--; $heal = floor($player['base_stats']['max_hp'] * 0.4); 
                $player['hp'] = min($player['base_stats']['max_hp'], $player['hp'] + $heal);
                $state['meters']['player'] = 0;
            }
        }

        if ($monster['hp'] <= 0) {
            $monster['hp'] = 0; $state['game_status']['game_over'] = true;
            $exp = $monster['exp_reward']; $gold = $monster['gold_reward'];
            $player['exp'] += $exp; $player['gold'] += $gold;
            $levelup = checkLevelUp($player); $player = $levelup['player'];
            $player['completed_events'][$player['current_map_id']]["{$player['current_map_pos_y']},{$player['current_map_pos_x']}"] = true;
            savePlayerState($pdo, $player); // Salva progresso
            unset($_SESSION['battle_state']);
            echo json_encode(['view' => 'battle_over', 'log' => "Vitória! +$exp XP, +$gold Ouro.", 'battle_data' => packageStateForFrontend($state), 'hero_id' => $player['hero_id_key']]); exit;
        }
        echo json_encode(['view' => 'battle', 'battle_data' => packageStateForFrontend($state)]); exit;
    }
}

// ============================================================================
// 6. LOJA E PERSONAGEM (FORA DE BATALHA)
// ============================================================================

if ($action === 'distribute_point') {
    $hero_id = $postData['hero_id'];
    $attribute = $postData['attribute'];
    
    $player = getPlayerState($pdo, $hero_id, $user_id);
    
    if ($player['attribute_points'] > 0) {
        
        // Garante inicialização das chaves se não existirem
        if(!isset($player['base_stats']['strength'])) $player['base_stats']['strength'] = 0;
        if(!isset($player['base_stats']['agility'])) $player['base_stats']['agility'] = 0;
        if(!isset($player['base_stats']['luck'])) $player['base_stats']['luck'] = 0;

        switch ($attribute) {
            case 'strength': 
                $player['base_stats']['strength']++; 
                break;
                
            case 'agility': 
                // CORREÇÃO: Aumenta 'agility', não 'speed'
                $player['base_stats']['agility']++; 
                break;
                
            case 'luck': 
                $player['base_stats']['luck']++; 
                break;
                
            case 'vitality': 
                $player['base_stats']['max_hp'] += 10; 
                $player['hp'] += 10; 
                break;
        }
        
        $player['attribute_points']--;
        
        // Recalcula para atualizar os Combat Stats com os novos valores
        $player = recalculate_player_stats($player);
        
        // Salva no banco
        savePlayerState($pdo, $player);
        
        echo json_encode([
            'view' => 'character_update', 
            'player' => $player, 
            'log' => "Atributo melhorado!"
        ]);
    } else {
        echo json_encode(['error' => 'Sem pontos.']);
    }
    exit;
}

if ($action === 'buy_item') {
    $hero_id = $postData['hero_id'];
    $item = $postData['item'];
    
    // CORREÇÃO: Busca herói do usuário logado
    $player = getPlayerState($pdo, $hero_id, $user_id);
    
    $PRICES = ['potion' => 50, 'attribute_point' => 150];
    if (isset($PRICES[$item]) && $player['gold'] >= $PRICES[$item]) {
        $player['gold'] -= $PRICES[$item];
        if ($item === 'potion') $player['potions']++;
        if ($item === 'attribute_point') $player['attribute_points']++;
        
        savePlayerState($pdo, $player);
        echo json_encode(['view' => 'shop_update', 'player' => $player, 'success' => true, 'log' => "Comprado!"]);
    } else {
        echo json_encode(['error' => 'Ouro insuficiente.']);
    }
    exit;
}

if ($action === 'get_shop_data') {
    $hero_id = $postData['hero_id'];
    // CORREÇÃO: Busca herói do usuário logado
    $player = getPlayerState($pdo, $hero_id, $user_id);
    echo json_encode(['view' => 'open_shop', 'player' => $player]);
    exit;
}

// Função auxiliar de pacote (se não estiver em logic)
function packageStateForFrontend($state) {
    // ... (mantido igual ao anterior) ...
    $all_heroes = require __DIR__ . '/data/heroes.php';
    $player = $state['player'];
    $monster = $state['monster'];
    $flat = $state['game_status'];
    $flat['player_hit'] = $state['turn_flags']['player_hit'];
    $flat['monster_hit'] = $state['turn_flags']['monster_hit'];
    $flat['monster_healed'] = $state['turn_flags']['monster_healed'] ?? false;
    $flat['player_def_stacks'] = $state['defense_stacks']['player'];
    $flat['monster_def_stacks'] = $state['defense_stacks']['monster'];
    $flat['player_def_cd'] = $state['defense_cooldown']['player'];
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