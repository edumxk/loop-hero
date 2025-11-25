<?php
/**
 * /api/logic/map_logic.php
 * Lida com a lógica de movimentação e eventos de mapa
 */

function loadMapData($map_id) {
    $path = __DIR__ . "/../data/maps/{$map_id}.php";
    if (!file_exists($path)) throw new Exception("Mapa não encontrado: $map_id");
    return require $path;
}

function processMove(&$player, $direction) {
    $current_map = loadMapData($player['current_map_id']);
    $new_x = $player['current_map_pos_x'];
    $new_y = $player['current_map_pos_y'];

    // 1. Calcula Nova Posição
    switch ($direction) {
        case 'up': $new_y--; break;
        case 'down': $new_y++; break;
        case 'left': $new_x--; break;
        case 'right': $new_x++; break;
        default: return ['status' => 'invalid_move', 'log' => 'Direção inválida.', 'player' => $player];
    }

    // 2. Validação de Limites e Paredes (0)
    if (!isset($current_map['map'][$new_y][$new_x]) || $current_map['map'][$new_y][$new_x] === 0) {
        return ['status' => 'blocked', 'log' => "Caminho bloqueado.", 'player' => $player];
    }

    // 3. Atualiza Posição
    $player['current_map_pos_x'] = $new_x;
    $player['current_map_pos_y'] = $new_y;

    // 4. Verifica o Tipo de Terreno
    $cell_type = $current_map['map'][$new_y][$new_x];

    // --- LÓGICA DE SAÍDA (TRANSITION) ---
    if ($cell_type === 'E') {
        // Define o próximo mapa (Loop: map1 <-> map2)
        // Você pode expandir isso para map3, map4 etc.
        $next_map_id = ($player['current_map_id'] === 'map1') ? 'map2' : 'map1';
        
        // Carrega o próximo mapa para saber onde é o inicio ('S')
        $next_map_data = loadMapData($next_map_id);
        
        // Atualiza o jogador para o novo mapa
        $player['current_map_id'] = $next_map_id;
        $player['current_map_pos_x'] = $next_map_data['start_pos']['x'];
        $player['current_map_pos_y'] = $next_map_data['start_pos']['y'];
        $player['completed_events'] = []; // Reseta eventos completados para o novo mapa

        return [
            'status' => 'map_switch', // Status especial para o Frontend animar
            'log' => "Você viajou para uma nova região.",
            'new_map_data' => $next_map_data, // Envia o novo mapa para desenhar
            'player' => $player
        ];
    }

    // 5. Verifica Eventos (Monstros, Lojas, Tesouros, Armadilhas)
    $event_key = "$new_y,$new_x";
    $map_id = $player['current_map_id'];
    
    if (isset($current_map['events'][$event_key])) {
        $event = $current_map['events'][$event_key];
        
        // Se o evento já foi completado, não faz nada especial
        if (isset($player['completed_events'][$map_id][$event_key])) {
            return ['status' => 'moved', 'log' => "Local seguro.", 'player' => $player];
        }

        // Prepara resposta base com o evento para o JS (para animações/delays)
        $response = [
            'status' => 'moved', 
            'event' => $event, 
            'log' => "Algo chamou sua atenção...",
            'player' => $player
        ];

        // Processa eventos instantâneos aqui
        if ($event['type'] === 'trap') {
            $damage = $event['damage'] ?? 10;
            $player['hp'] -= $damage;
            $response['log'] = "Você ativou uma armadilha e perdeu {$damage} HP!";
            if ($player['hp'] <= 0) $player['hp'] = 1; // Deixa com 1 de vida pra não morrer no mapa
            
            // Marca como concluído
            $player['completed_events'][$map_id][$event_key] = true;
        }
        
        else if ($event['type'] === 'treasure') {
            $treasure_type = $event['treasure_type'] ?? 'gold';
            $value = $event['value'] ?? 0;

            switch ($treasure_type) {
                case 'gold':
                    $player['gold'] += $value;
                    $response['log'] = "Você encontrou um tesouro! (+{$value} Ouro)";
                    break;
                case 'potion':
                    $player['potions'] += $value;
                    $response['log'] = "Você encontrou {$value} poção(ões)!";
                    break;
                default:
                    $response['log'] = "Você encontrou algo misterioso...";
            }
            
            // Marca como concluído
            $player['completed_events'][$map_id][$event_key] = true;
        }
        
        // Nota: Monstros e Lojas são tratados pelo JS (trigger_battle / open_shop),
        // então não marcamos como concluído aqui.
        
        // Atualiza o player na resposta caso tenha mudado HP/Gold
        $response['player'] = $player;
        return $response;
    }

    return ['status' => 'moved', 'log' => "", 'player' => $player];
}
?>