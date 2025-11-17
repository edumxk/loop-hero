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

// 4. INICIAR BATALHA (Trigger)
if ($action === 'trigger_battle') {
    try {
        // Validação de dados recebidos
        $hero_id = $postData['hero_id'] ?? null;
        if (!$hero_id) throw new Exception("Hero ID não fornecido.");

        $difficulty = $postData['difficulty'] ?? 'easy';
        $monster_id = $postData['monster_id'] ?? null;
        
        $player = getPlayerState($pdo, $hero_id);
        
        // --- TENTA CRIAR O MONSTRO ---
        // Usamos 'try/catch' aqui para pegar erros na lógica do monstro
        try {
            $monster = spawnMonster($player['level'], $difficulty, $monster_id);
        } catch (ArgumentCountError $e) {
            throw new Exception("Erro no código: spawnMonster espera argumentos diferentes. Atualize monster_logic.php");
        } catch (Exception $e) {
            throw new Exception("Erro ao criar monstro: " . $e->getMessage());
        }

        // Garante atributos padrão para o sistema ATB
        if (!isset($player['base_stats']['speed'])) $player['base_stats']['speed'] = 10;
        if (!isset($monster['stats']['speed'])) $monster['stats']['speed'] = 8;
        if (!isset($monster['stats']['potions'])) $monster['stats']['potions'] = 0;

        $_SESSION['battle_state'] = [
            'player' => $player,
            'monster' => $monster,
            'meters' => ['player' => 0, 'monster' => 0], // ATB começa em 0
            'game_status' => ['game_over' => false, 'log' => "Um(a) {$monster['name']} apareceu!"],
            'turn_flags' => ['player_defending' => false, 'player_hit' => false, 'monster_hit' => false]
        ];
        
        echo json_encode([
            'view' => 'battle',
            'battle_data' => packageStateForFrontend($_SESSION['battle_state'])
        ]);
        exit;

    } catch (Throwable $e) { // 'Throwable' pega Erros Fatais e Exceções
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// ============================================================================
// 5. SISTEMA DE BATALHA (ATB)
// ============================================================================

// Se não houver batalha na sessão, retorna erro (exceto para as ações acima)
if (!isset($_SESSION['battle_state'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Nenhuma batalha em andamento.']);
    exit;
}

$state = &$_SESSION['battle_state'];
$player = &$state['player'];
$monster = &$state['monster'];

// Se o jogo já acabou, retorna o estado final sem processar
if ($state['game_status']['game_over']) {
    echo json_encode([
        'view' => 'battle_over',
        'battle_data' => packageStateForFrontend($state),
        'log' => $state['game_status']['log'],
        'hero_id' => ($player['hp'] > 0 ? $player['hero_id_key'] : null)
    ]);
    exit;
}

// --- AÇÃO ESPECIAL: TICK (O Relógio da Batalha) ---
if ($action === 'tick') {
    $log = [];
    $something_happened = false;

    // Limpa flags de animação antigas
    $state['turn_flags']['player_hit'] = false;
    $state['turn_flags']['monster_hit'] = false;

    // 1. Enche as Barras (Soma Velocidade)
    // Só soma se a barra ainda não estiver cheia (100)
    if ($state['meters']['player'] < 100) {
        $state['meters']['player'] += $player['base_stats']['speed'];
        if ($state['meters']['player'] > 100) $state['meters']['player'] = 100;
    }

    if ($state['meters']['monster'] < 100) {
        $state['meters']['monster'] += $monster['stats']['speed'];
        if ($state['meters']['monster'] > 100) $state['meters']['monster'] = 100;
    }

    // 2. IA DO MONSTRO (Age automaticamente se barra >= 100)
    if ($state['meters']['monster'] >= 100) {
        $something_happened = true;
        $state['meters']['monster'] = 0; // Consome a barra do monstro

        $monster_hp_percent = ($monster['hp'] / $monster['stats']['max_hp']) * 100;
        
        // Lógica IA: Se HP < 50% e tem poção -> Usa Poção
        if ($monster_hp_percent < 50 && $monster['stats']['potions'] > 0) {
            $monster['stats']['potions']--;
            $heal = floor($monster['stats']['max_hp'] * 0.3); // Cura 30%
            $monster['hp'] = min($monster['stats']['max_hp'], $monster['hp'] + $heal);
            $log[] = "O {$monster['name']} usou uma poção e recuperou $heal HP!";
        } 
        // Lógica IA: Senão -> Ataca
        else {
            $result = calculateBattleDamage($monster['stats'], $player['combat_stats']);
            $dmg = $result['damage'];
            
            if ($state['turn_flags']['player_defending']) {
                $dmg = floor($dmg / 2);
                $log[] = "{$monster['name']} atacou, mas você defendeu! ($dmg dano)";
                $state['turn_flags']['player_defending'] = false; // Defesa gasta
            } else {
                $log[] = "{$monster['name']} atacou! ($dmg dano)";
            }
            
            $player['hp'] -= $dmg;
            $state['turn_flags']['player_hit'] = true;
        }

        // Verifica Morte do Jogador (Permadeath)
        if ($player['hp'] <= 0) {
            $player['hp'] = 0;
            $state['game_status']['game_over'] = true;
            $log[] = "Você foi derrotado! Seu herói caiu.";
            
            // Deleta do DB
            $stmt = $pdo->prepare("DELETE FROM heroes WHERE id = ?");
            $stmt->execute([$player['id']]);
            
            $state['game_status']['log'] = implode(' ', $log);
            $final_data = packageStateForFrontend($state);
            unset($_SESSION['battle_state']);

            echo json_encode([
                'view' => 'battle_over',
                'log' => implode(' ', $log),
                'battle_data' => $final_data,
                'hero_id' => null
            ]);
            exit;
        }
    }

    if ($something_happened) {
        $state['game_status']['log'] = implode(' ', $log);
    }

    echo json_encode([
        'view' => 'battle',
        'battle_data' => packageStateForFrontend($state)
    ]);
    exit;
}

// --- AÇÕES DO JOGADOR (Attack, Defend, Potion) ---
if ($action === 'attack' || $action === 'defend' || $action === 'potion') {
    
    // Validação: Só pode agir se a barra estiver cheia
    if ($state['meters']['player'] < 100) {
        echo json_encode(['error' => 'Aguarde sua barra de ação encher!']);
        exit;
    }

    $log = [];
    $state['turn_flags']['player_hit'] = false;
    $state['turn_flags']['monster_hit'] = false;

    switch ($action) {
        case 'attack':
            $result = calculateBattleDamage($player['combat_stats'], $monster['stats']);
            $monster['hp'] -= $result['damage'];
            $state['turn_flags']['monster_hit'] = true;
            $log[] = "Você atacou e " . $result['log'];
            $state['meters']['player'] = 0; // Zera a barra após agir
            break;
            
        case 'defend':
            $state['turn_flags']['player_defending'] = true;
            $log[] = "Você assumiu postura defensiva.";
            $state['meters']['player'] = 0; // Zera a barra após agir
            break;
            
        case 'potion':
            if ($player['potions'] > 0) {
                $player['potions']--;
                $heal = floor($player['base_stats']['max_hp'] * 0.4); 
                $player['hp'] = min($player['base_stats']['max_hp'], $player['hp'] + $heal);
                $log[] = "Você usou uma poção e curou $heal HP.";
                $state['meters']['player'] = 0; // Zera a barra após agir
            } else {
                $log[] = "Você não tem poções!";
                // Não zera a barra se falhar o uso
            }
            break;
    }

    // Verifica Morte do Monstro
    if ($monster['hp'] <= 0) {
        $monster['hp'] = 0;
        $state['game_status']['game_over'] = true;
        
        $final_log = [];
        $exp_gained = $monster['exp_reward'];
        $player['exp'] += $exp_gained;
        $final_log[] = "Você derrotou o(a) {$monster['name']}!";
        $final_log[] = "Ganhou $exp_gained EXP.";
        
        $gold_gained = $monster['gold_reward'];
        $player['gold'] += $gold_gained;
        $final_log[] = "Ganhou $gold_gained de Ouro.";

        $levelup_result = checkLevelUp($player);
        $player = $levelup_result['player'];
        if (!empty($levelup_result['log'])) {
            $final_log[] = $levelup_result['log'];
        }

        // Marca evento do mapa como concluído
        $map_id = $player['current_map_id'];
        $event_key = "{$player['current_map_pos_y']},{$player['current_map_pos_x']}";
        $player['completed_events'][$map_id][$event_key] = true;

        savePlayerState($pdo, $player);
        
        $state['game_status']['log'] = implode(' ', $final_log);
        $final_data = packageStateForFrontend($state);
        unset($_SESSION['battle_state']);
        
        echo json_encode([
            'view' => 'battle_over',
            'log' => implode(' ', $final_log),
            'battle_data' => $final_data,
            'hero_id' => $player['hero_id_key']
        ]);
        exit;
    }

    $state['game_status']['log'] = implode(' ', $log);

    echo json_encode([
        'view' => 'battle',
        'battle_data' => packageStateForFrontend($state)
    ]);
    exit;
}

// --- FUNÇÃO DE PACOTE PARA O FRONTEND ---
function packageStateForFrontend($state) {
    $all_heroes = require __DIR__ . '/data/heroes.php';
    $player = $state['player'];
    $monster = $state['monster'];
    
    $flat_state = $state['game_status'];
    $flat_state['player_hit'] = $state['turn_flags']['player_hit'];
    $flat_state['monster_hit'] = $state['turn_flags']['monster_hit'];
    
    // Envia os medidores ATB
    $flat_state['player_defending'] = $state['turn_flags']['player_defending'];
    $flat_state['meters'] = $state['meters'];

    // Dados Player
    $player_crit = ($player['combat_stats']['crit_chance'] ?? 0) * 100;
    $flat_state['player_name'] = ($player['class_name'] ?? '?') . " (Lvl " . ($player['level'] ?? '?') . ")";
    $flat_state['player_hp'] = $player['hp'] ?? 0;
    $flat_state['player_max_hp'] = $player['base_stats']['max_hp'] ?? 100;
    $flat_state['player_sprite_folder'] = $all_heroes[$player['hero_id_key']]['sprite_folder'] ?? 'assets/heroes/paladino/';
    $flat_state['player_hp_percent'] = ($flat_state['player_max_hp'] > 0) ? ($flat_state['player_hp'] / $flat_state['player_max_hp']) * 100 : 0;
    $flat_state['potions'] = $player['potions'] ?? 0;
    $flat_state['player_stats'] = [
        'attack' => $player['combat_stats']['attack'] ?? 0,
        'defense' => $player['combat_stats']['defense'] ?? 0,
        'crit_chance' => $player_crit
    ];

    // Dados Monstro
    $monster_crit = ($monster['stats']['crit_chance'] ?? 0) * 100;
    $flat_state['monster_name'] = $monster['name'] ?? "?";
    $flat_state['monster_hp'] = $monster['hp'] ?? 0;
    $flat_state['monster_max_hp'] = $monster['stats']['max_hp'] ?? 100;
    $flat_state['monster_sprite_folder'] = $monster['sprite_folder'] ?? 'assets/monsters/goblin/';
    $flat_state['monster_hp_percent'] = ($flat_state['monster_max_hp'] > 0) ? ($flat_state['monster_hp'] / $flat_state['monster_max_hp']) * 100 : 0;
    $flat_state['monster_stats'] = [
        'attack' => $monster['stats']['attack'] ?? 0,
        'defense' => $monster['stats']['defense'] ?? 0,
        'crit_chance' => $monster_crit
    ];
    
    return $flat_state;
}
?>