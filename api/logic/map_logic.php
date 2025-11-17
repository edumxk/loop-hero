<?php
/**
 * /api/logic/map_logic.php
 * Lida com a lógica de mapa e eventos.
 * (Versão limpa, sem texto fora das tags)
 */

function loadMapData($mapId) {
    $mapFile = __DIR__ . "/../data/maps/{$mapId}.php";
    if (!file_exists($mapFile)) {
        throw new Exception("Mapa '$mapId' não encontrado.");
    }
    return require $mapFile;
}

/**
 * Tenta mover o jogador e processa o evento da nova célula.
 */
function processMove($player, $direction) {
    $map = loadMapData($player['current_map_id']);
    
    $x = $player['current_map_pos_x'];
    $y = $player['current_map_pos_y'];

    // Calcula a nova posição
    switch ($direction) {
        case 'up':    $y--; break;
        case 'down':  $y++; break;
        case 'left':  $x--; break;
        case 'right': $x++; break;
        default: return ['status' => 'invalid_move', 'log' => 'Direção inválida.'];
    }

    // Verifica se a nova posição é válida
    if (!isset($map['map'][$y][$x]) || $map['map'][$y][$x] === 0) {
        return [
            'status' => 'blocked',
            'log' => 'Você bateu na parede.',
            'player' => $player // Retorna o jogador original
        ];
    }

    // A Posição é válida. Atualiza o jogador.
    $player['current_map_pos_x'] = $x;
    $player['current_map_pos_y'] = $y;

    // Prepara a resposta
    $response = [
        'status' => 'moved',
        'player' => $player,
        'log' => 'Você se moveu.'
    ];

    // Verifica se a célula é o FIM
    if ($map['map'][$y][$x] === 'E') {
        $response['status'] = 'level_complete';
        $response['log'] = 'Você encontrou a saída!';
        return $response;
    }

    // --- LÓGICA DE EVENTO ATUALIZADA ---
    $event_key = "$y,$x";
    $map_id = $player['current_map_id'];

    // 1. Verifica se o evento existe E se NÃO foi concluído
    if (isset($map['events'][$event_key]) && 
        !isset($player['completed_events'][$map_id][$event_key])) {
        
        $event = $map['events'][$event_key];
        $response['event'] = $event; // Anexa o evento para o JS (para o atraso)

        // 2. Processa eventos instantâneos (Armadilha, Tesouro)
        if ($event['type'] === 'trap') {
            $player['hp'] -= $event['damage'];
            $response['log'] = "Você ativou uma armadilha e perdeu {$event['damage']} HP!";
            if ($player['hp'] <= 0) $player['hp'] = 1;
            // Marca como concluído
            $player['completed_events'][$map_id][$event_key] = true;
        }
        
        if ($event['type'] === 'treasure') {
            
            $treasure_type = $event['treasure_type'] ?? 'gold'; // O padrão é 'gold'
            $value = $event['value'];

            // Verifica o tipo de tesouro e aplica o bônus
            switch ($treasure_type) {
                case 'gold':
                    $player['gold'] += $value;
                    $response['log'] = "Você encontrou um tesouro! (+{$value} Ouro)";
                    break;
                
                case 'potion':
                    $player['potions'] += $value;
                    $response['log'] = "Você encontrou {$value} poção(ões)!";
                    break;
                
                // (Você pode adicionar 'case "key":' etc. aqui no futuro)
                
                default:
                    $response['log'] = "Você encontrou um tesouro misterioso...";
            }
            
            // Marca o evento como concluído
            $player['completed_events'][$map_id][$event_key] = true;
        }
        
        if ($event['type'] === 'monster') {
            // Não marca como concluído ainda.
            // O JS vai chamar 'trigger_battle'.
            // Vamos marcar como concluído APÓS a batalha (no game.php)
        }
    }
    
    $response['player'] = $player;
    return $response;
}