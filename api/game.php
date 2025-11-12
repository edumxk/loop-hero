<?php
session_start();
header('Content-Type: application/json');

// --- CARREGAR RECURSOS ---
// (Os 'require_once' agora devem funcionar com o 'database.php' corrigido)
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/logic/battle_logic.php';
require_once __DIR__ . '/logic/player_logic.php';
require_once __DIR__ . '/logic/monster_logic.php';
require_once __DIR__ . '/logic/map_logic.php'; // <-- ADICIONE ESTA LINHA

// --- CONEXÃO COM DB ---
// getDbConnection() agora também inicializa o DB se for a 1ª vez
$pdo = getDbConnection();

// --- PEGAR AÇÃO E DADOS ---
$action = $_GET['action'] ?? 'default';
$postData = json_decode(file_get_contents('php://input'), true) ?? [];

// --- ROTEADOR DE AÇÕES ---

// == NOVA AÇÃO: VERIFICAR SAVES ==
if ($action === 'check_saves') {
    // Simplesmente busca todos os heróis existentes no DB
    $stmt = $pdo->query("SELECT hero_id_key, class_name, level FROM heroes ORDER BY level DESC");
    $heroes = $stmt->fetchAll();
    echo json_encode($heroes); // Retorna um array de heróis salvos
    exit;
}

// == AÇÃO: INICIAR BATALHA ==
if ($action === 'load_game' || $action === 'start') {
    try {
        $hero_id = $postData['hero_id'] ?? 'human_knight';
        
        if ($action === 'start') {
            // 'start' (Novo Jogo) DELETA o save antigo e cria um novo
            // (Poderia ser mais complexo, mas isso funciona)
            $stmt = $pdo->prepare("DELETE FROM heroes WHERE hero_id_key = ?");
            $stmt->execute([$hero_id]);
            $player = createNewPlayer($pdo, $hero_id);
        } else {
            // 'load_game' (Continuar) apenas carrega o save
            $player = getPlayerState($pdo, $hero_id);
        }

        // Carrega os dados do mapa atual do jogador
        $map = loadMapData($player['current_map_id']);
        
        // Retorna o estado completo do MAPA
        echo json_encode([
            'view' => 'map', // Diz ao JS qual tela mostrar
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

if ($action === 'trigger_battle') {
   try {
        // ================================================================
        // A CORREÇÃO ESTÁ AQUI
        // ================================================================
        $hero_id = $postData['hero_id'];
        // 1. Recebe a dificuldade do JS
        $difficulty = $postData['difficulty'] ?? 'easy';
        
        $player = getPlayerState($pdo, $hero_id);
        
        // 2. Passa a dificuldade para o spawnMonster
        $monster = spawnMonster($player['level'], $difficulty);
        // ================================================================

        $_SESSION['battle_state'] = [
            'player' => $player,
            'monster' => $monster,
            'game_status' => ['game_over' => false, 'log' => "Um(a) $monster[name] apareceu!"],
            'turn_flags' => ['player_defending' => false, 'player_hit' => false, 'monster_hit' => false]
        ];
        
        echo json_encode([
            'view' => 'battle',
            'battle_data' => packageStateForFrontend($_SESSION['battle_state'])
        ]);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// --- AÇÃO DE MOVIMENTAÇÃO NO MAPA ---
if ($action === 'move') {
    try {
        $direction = $postData['direction'] ?? null;
        if (!$direction) throw new Exception('Direção não fornecida.');

        $hero_id = $postData['hero_id'];
        $player = getPlayerState($pdo, $hero_id);

        $result = processMove($player, $direction);
        $player = $result['player']; // Pega o jogador (talvez com dano de armadilha)

        // Salva o novo estado (HP, Posição, Ouro) no DB
        savePlayerState($pdo, $player); 

        // 4. Verifica se o evento é uma batalha
        // (Esta lógica foi REMOVIDA daqui)

        // 5. Retorna o resultado do movimento
        $response = [
            'view' => 'map', // Continua no mapa
            'status' => $result['status'],
            'log' => $result['log'],
            'player_pos' => [ // Envia a nova posição
                'x' => $player['current_map_pos_x'],
                'y' => $player['current_map_pos_y']
            ],
            // Envia o HUD atualizado (para armadilhas, tesouros, etc.)
            'hud_update' => [
                'hp' => $player['hp'],
                'max_hp' => $player['base_stats']['max_hp'],
                'gold' => $player['gold'],
                'potions' => $player['potions']
            ]
        ];
        
        // Se a 'processMove' encontrou um evento, anexe-o à resposta
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
// --- LÓGICA DE TURNO ---

if (!isset($_SESSION['battle_state'])) {
    // Se não for 'start' e não houver batalha, é um erro
    if ($action !== 'start' && $action !== 'check_saves') {
        http_response_code(400);
        echo json_encode(['error' => 'Nenhuma batalha em andamento. Inicie um novo jogo.']);
        exit;
    }
    // Se for 'start', o código de batalha abaixo será pulado
    // e o estado criado acima será enviado no final
} else {
    // Carrega o estado da SESSÃO se a batalha estiver em andamento
    $state = &$_SESSION['battle_state'];
    $player = &$state['player'];
    $monster = &$state['monster'];

    // Se o jogo já acabou, não processa
    if ($state['game_status']['game_over']) {
        echo json_encode(packageStateForFrontend($state));
        exit;
    }

    $log = [];
    $state['turn_flags']['player_defending'] = false;
    $state['turn_flags']['player_hit'] = false;
    $state['turn_flags']['monster_hit'] = false;

    // --- AÇÃO DO JOGADOR ---
    switch ($action) {
        case 'attack':
            $result = calculateBattleDamage($player['combat_stats'], $monster['stats']);
            $monster['hp'] -= $result['damage'];
            $state['turn_flags']['monster_hit'] = true;
            $log[] = "Você ataca e " . $result['log'];
            break;
        case 'defend':
            $state['turn_flags']['player_defending'] = true;
            $log[] = "Você se defende.";
            break;
        case 'potion':
            if ($player['potions'] > 0) {
                $player['potions']--; // Usa uma poção
                
                // Cura 40% do HP máximo (você pode mudar esse valor)
                $heal_amount = floor($player['base_stats']['max_hp'] * 0.2); 
                // Cura, mas não deixa passar do HP máximo
                $player['hp'] = min($player['base_stats']['max_hp'], $player['hp'] + $heal_amount);
                
                $log[] = "Você usou uma poção e curou $heal_amount HP. ({$player['potions']} restantes)";
            } else {
                $log[] = "Você não tem mais poções!";
            }
            break;
        default:
            $log[] = "Ação inválida.";
            echo json_encode(packageStateForFrontend($state));
            exit;
    }

    // --- VERIFICA SE O MONSTRO MORREU ---
    if ($monster['hp'] <= 0) { // Se o monstro morreu
        
        $monster['hp'] = 0; // Zera o HP
        $state['game_status']['game_over'] = true;
        
        // 1. Cria o "resumo de vitória"
        $final_log = [];
        $exp_gained = $monster['exp_reward'];
        $player['exp'] += $exp_gained;
        $final_log[] = "Você derrotou o(a) {$monster['name']}!";
        $final_log[] = "Ganhou $exp_gained EXP.";
        $gold_gained = $monster['gold_reward'];
        $player['gold'] += $gold_gained;
        $final_log[] = "Ganhou $gold_gained de Ouro.";

        // 2. Verifica Level Up
        $levelup_result = checkLevelUp($player);
        $player = $levelup_result['player'];
        if (!empty($levelup_result['log'])) {
            $final_log[] = $levelup_result['log'];
        }
        
        // --- MARCA O EVENTO DE MONSTRO COMO CONCLUÍDO ---
        $map_id = $player['current_map_id'];
        $event_key = "{$player['current_map_pos_y']},{$player['current_map_pos_x']}";
        $player['completed_events'][$map_id][$event_key] = true;
        // --- FIM DA ATUALIZAÇÃO ---

        // 3. Salva o progresso no DB
        savePlayerState($pdo, $player);
        
        // === A MUDANÇA ESTÁ AQUI ===
        
        // 4. Define o log final no estado (para packageState)
        $state['game_status']['log'] = implode(' ', $final_log);
        
        // 5. Formata o estado final (com HP do monstro em 0)
        $final_battle_data = packageStateForFrontend($state);
        
        // 6. Limpa a sessão
        unset($_SESSION['battle_state']);
        
        // 7. Envia a view 'battle_over' E os dados finais
        echo json_encode([
            'view' => 'battle_over',
            'log' => implode(' ', $final_log), // Log para a mensagem
            'battle_data' => $final_battle_data, // Dados para a UI
            'hero_id' => $player['hero_id_key']
        ]);
        exit;
    }

    // --- AÇÃO DO MONSTRO ---
    $result = calculateBattleDamage($monster['stats'], $player['combat_stats']);
    $damage_received = $result['damage'];

    if ($state['turn_flags']['player_defending']) {
        $damage_received = max(1, floor($damage_received / 2));
        $log[] = "O(A) {$monster['name']} ataca, mas você defende e recebe apenas $damage_received de dano.";
    } else {
        $log[] = "O(A) {$monster['name']} ataca e " . $result['log'];
    }

    $player['hp'] -= $damage_received;
    $state['turn_flags']['player_hit'] = true;

    
    // --- VERIFICA SE O JOGADOR MORREU ---
   if ($player['hp'] <= 0) {
        $player['hp'] = 0; // Zera o HP
        $state['game_status']['game_over'] = true;
        $log[] = "Você foi derrotado! Fim de jogo.";

        // ================================================================
        // A CORREÇÃO ESTÁ AQUI (PERMADEATH)
        // ================================================================
        
        // 1. Deleta o herói do DB.
        $stmt = $pdo->prepare("DELETE FROM heroes WHERE id = ?");
        $stmt->execute([$player['id']]);
        
        // 2. Define o log final no estado
        $state['game_status']['log'] = implode(' ', $log);
        
        // 3. Formata o estado final (com HP do jogador em 0)
        $final_battle_data = packageStateForFrontend($state);

        // 4. Limpa a sessão
        unset($_SESSION['battle_state']);
        
        // 5. Envia a resposta de 'battle_over' (AGORA COM 'battle_data')
        echo json_encode([
            'view' => 'battle_over',
            'log' => implode(' ', $log), // "Você foi derrotado!"
            'battle_data' => $final_battle_data, // <-- A CHAVE FALTANTE
            'hero_id' => null // Indica permadeath
        ]);
        exit;
    }

    // Junta todas as mensagens do log
    $state['game_status']['log'] = implode(' ', $log);

        // ================================================================
        // A CORREÇÃO ESTÁ AQUI
        // ================================================================
        // Empacota a resposta da batalha para o roteador do JS
        $battle_data = packageStateForFrontend($state);
        echo json_encode([
            'view' => 'battle', // Diz ao JS para continuar na tela de batalha
            'battle_data' => $battle_data
        ]);
    exit; // Termina o script aqui
}

// Envia a resposta final (formatada)
// (Isso pegará o estado da 'start' ou o estado do turno)
echo json_encode(packageStateForFrontend($_SESSION['battle_state']));


/**
 * Função final para "achatar" o estado da sessão para o frontend
 */
function packageStateForFrontend($state) {
    // CORREÇÃO: Carrega $all_heroes aqui dentro para garantir o acesso ao 'sprite_url'
    $all_heroes = require __DIR__ . '/data/heroes.php';

    $player = $state['player'];
    $monster = $state['monster'];
    
    $flat_state = $state['game_status'];
    $flat_state['player_hit'] = $state['turn_flags']['player_hit'];
    $flat_state['monster_hit'] = $state['turn_flags']['monster_hit'];

    if (isset($state['_debug_info'])) {
        $flat_state['_debug'] = $state['_debug_info'];
        unset($_SESSION['battle_state']['_debug_info']);
    }

    // --- ATUALIZAÇÃO DO JOGADOR (Adicionado 'player_stats') ---
    $player_crit_chance = ($player['combat_stats']['crit_chance'] ?? 0) * 100; // Converte 0.1 para 10%
    $flat_state['player_name'] = ($player['class_name'] ?? 'Erro') . " (Lvl " . ($player['level'] ?? '?') . ")";
    $flat_state['player_hp'] = $player['hp'] ?? 0;
    $flat_state['player_max_hp'] = $player['base_stats']['max_hp'] ?? 100;
    $flat_state['player_sprite'] = $all_heroes[$player['hero_id_key']]['sprite_url'] ?? 'assets/heroes/default.png';
    $flat_state['player_hp_percent'] = ($flat_state['player_max_hp'] > 0) ? ($flat_state['player_hp'] / $flat_state['player_max_hp']) * 100 : 0;
    $flat_state['player_stats'] = [
        'attack' => $player['combat_stats']['attack'] ?? 0,
        'defense' => $player['combat_stats']['defense'] ?? 0,
        'crit_chance' => $player_crit_chance
    ];
    // --- CORREÇÃO DO TODO ---
    $flat_state['potions'] = $player['potions'] ?? 0;
    // --- FIM DA CORREÇÃO ---

    // --- ATUALIZAÇÃO DO MONSTRO (Adicionado 'monster_stats') ---
    $monster_crit_chance = ($monster['stats']['crit_chance'] ?? 0) * 100; // Converte 0.1 para 10%
    $flat_state['monster_name'] = $monster['name'] ?? "Monstro Quebrado";
    $flat_state['monster_hp'] = $monster['hp'] ?? 0;
    $flat_state['monster_max_hp'] = $monster['stats']['max_hp'] ?? 100;
    $flat_state['monster_sprite'] = $monster['sprite'] ?? 'assets/monsters/goblin.png';
    $flat_state['monster_hp_percent'] = ($flat_state['monster_max_hp'] > 0) ? ($flat_state['monster_hp'] / $flat_state['monster_max_hp']) * 100 : 0;
    $flat_state['monster_stats'] = [
        'attack' => $monster['stats']['attack'] ?? 0,
        'defense' => $monster['stats']['defense'] ?? 0,
        'crit_chance' => $monster_crit_chance
    ];
    
    return $flat_state;
}