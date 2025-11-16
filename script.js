document.addEventListener('DOMContentLoaded', () => {

    // --- Seletores de View ---
    
    const mainMenuView = document.getElementById('main-menu-view');
    const gameView = document.getElementById('game-view');
    const mapView = document.getElementById('map-view');

    // --- Variável de Estado Global do JS ---
    let currentPlayerId = null;

    // --- Seletores do Menu Principal ---
    const continueGameSection = document.getElementById('continue-game-section');
    const continueGameList = document.getElementById('continue-game-list');
    const newGameTitle = document.getElementById('new-game-title');
    
    // --- Seletores de Batalha ---
    const returnToMenuBtn = document.getElementById('return-to-menu-btn');
    const menuArea = document.getElementById('menu-area');
    const gameOverArea = document.getElementById('game-over-area');
    const gameOverMessage = document.getElementById('game-over-message');
    const battleButtons = document.querySelectorAll('#menu-area button');
    
    const playerNameText = document.getElementById('player-name-text');
    const playerHpBar = document.getElementById('player-hp-bar');
    const playerHpText = document.getElementById('player-hp-text');
    const playerSprite = document.getElementById('player-sprite');
    
    const monsterNameText = document.getElementById('monster-name-text');
    const monsterHpBar = document.getElementById('monster-hp-bar');
    const monsterHpText = document.getElementById('monster-hp-text');
    const monsterSprite = document.getElementById('monster-sprite');

    const potionCountEl = document.getElementById('potion-count');
    const gameLogEl = document.getElementById('game-log');

    // --- Seletores do Mapa ---
    const mapName = document.getElementById('map-name');
    const mapGridContainer = document.getElementById('map-grid-container'); // <-- MUDANÇA: Seleciona o Container
    const mapGrid = document.getElementById('map-grid');
    const mapLog = document.getElementById('map-log');
    const mapControls = document.getElementById('map-controls');

    // NOVOS Seletores de Stats do Jogador
    const playerStatAtk = document.getElementById('player-stat-atk');
    const playerStatDef = document.getElementById('player-stat-def');
    const playerStatCrit = document.getElementById('player-stat-crit');

    // NOVOS Seletores de Stats do Monstro
    const monsterStatAtk = document.getElementById('monster-stat-atk');
    const monsterStatDef = document.getElementById('monster-stat-def');
    const monsterStatCrit = document.getElementById('monster-stat-crit');

    // --- NOVOS Seletores de HUD ---
    const mapHudGold = document.getElementById('map-hud-gold');
    const mapHudPotions = document.getElementById('map-hud-potions');
    const mapHudHp = document.getElementById('map-hud-hp'); // <-- ADICIONE ESTE
    // ===================================================================
    // 1. LÓGICA DE ROTEAMENTO DE TELA (API)
    // ===================================================================
    
    // ===================================================================
    // 1. LÓGICA DE ROTEAMENTO DE TELA (API)
    // ===================================================================
    
    function handleApiResponse(response) {
        if (response.error) {
            alert("Erro da API: " + response.error);
            mapControls.style.pointerEvents = 'auto'; // Destrava em caso de erro
            return;
        }

        // Roteia para a view correta
        if (response.view === 'map') {
            
            // 1. Atualiza o mapa (se necessário)
            if(response.player && response.map_data) {
                currentPlayerId = response.player.hero_id_key;
                drawMapScreen(response.player, response.map_data);
            }
            // 2. Move o avatar
            if (response.player_pos) {
                movePlayerAvatar(response.player_pos.x, response.player_pos.y);
            }
            // 3. Atualiza o HUD (HP, Ouro, etc.)
            if (response.hud_update) {
                updateMapHud(response.hud_update);
            }
            // 4. Atualiza o Log do Mapa
            if (response.log) {
                 mapLog.innerText = response.log;
            }

            // ================================================================
            // A CORREÇÃO ESTÁ AQUI (Lógica de Evento)
            // ================================================================
            if (response.event) {
                // EVENTO TIPO MONSTRO: Trava os controles e espera 1s
                if (response.event.type === 'monster') {
                    mapLog.innerText = "Você encontrou um monstro! Preparando para a batalha...";
                    mapControls.style.pointerEvents = 'none'; // TRAVA
                    
                    // ================================================================
                    // A CORREÇÃO ESTÁ AQUI
                    // ================================================================
                    // 1. Pega a dificuldade E o novo monster_id do evento
                    const difficulty = response.event.difficulty || 'easy';
                    const monsterId = response.event.monster_id || null; // <-- Pega o ID
                    
                    setTimeout(() => {
                        // 2. Envia ambos para a API
                        sendAction('trigger_battle', { 
                            hero_id: currentPlayerId,
                            difficulty: difficulty,
                            monster_id: monsterId // <-- Envia o ID
                        });
                    }, 500);
                    // ================================================================

                }
                
                // EVENTO TIPO ARMADILHA/TESOURO: Ação foi instantânea.
                else if (response.event.type === 'trap' || response.event.type === 'treasure') {
                    const x = response.player_pos.x;
                    const y = response.player_pos.y;
                    
                    // Em vez de calcular o índice, selecionamos pelo ID:
                    const cellToUpdate = document.getElementById(`cell-${y}-${x}`);
                    
                    if (cellToUpdate) {
                        cellToUpdate.classList.remove('event', 'monster-event'); // Remove '?'
                        cellToUpdate.classList.add('completed'); // Adiciona '✔️'
                    }
                    mapControls.style.pointerEvents = 'auto';
                }
                
            } else {
                // SEM EVENTO
                mapControls.style.pointerEvents = 'auto'; // DESTRAVA
            }
            // ================================================================

            showView('map-view');

        } else if (response.view === 'battle') {
            // Batalha carregou, reativa os controles do mapa (para quando a batalha acabar)
            mapControls.style.pointerEvents = 'auto';
            updateBattleScreen(response.battle_data);
            showView('game-view');

        } else if (response.view === 'battle_over') {
            // ... (lógica do 'battle_over' - não mude) ...
            if (response.battle_data) {
                updateBattleScreen(response.battle_data);
            }
            showView('game-view'); 
            menuArea.style.display = 'none';
            gameOverMessage.innerText = response.log;
            gameOverArea.style.display = 'block';
            
            if (response.hero_id === null) {
                currentPlayerId = null;
            }
        }
    }

    // ===================================================================
    // 2. LÓGICA DE CADA TELA
    // ===================================================================

    function updateMapHud(hudData) {
        if (hudData.gold !== undefined) {
            mapHudGold.innerText = hudData.gold;
        }
        if (hudData.potions !== undefined) {
            mapHudPotions.innerText = hudData.potions;
        }
        if (hudData.hp !== undefined && hudData.max_hp !== undefined) {
            mapHudHp.innerText = `${hudData.hp} / ${hudData.max_hp}`;
        }
    }
    // --- TELA DO MAPA ---
    function drawMapScreen(player, mapData) {
        mapName.innerText = mapData.name;
        
        updateMapHud({
            gold: player.gold,
            potions: player.potions,
            hp: player.hp,
            max_hp: player.base_stats.max_hp
        });
        
        mapGrid.innerHTML = ''; // Limpa o mapa
        
        mapGrid.style.gridTemplateColumns = `repeat(${mapData.map[0].length}, 40px)`;
        
        let playerAvatar = document.getElementById('player-avatar');
        if (!playerAvatar) {
            playerAvatar = document.createElement('div');
            playerAvatar.id = 'player-avatar';
            mapGridContainer.appendChild(playerAvatar);
        }

        // Desenha as células do mapa
        mapData.map.forEach((row, y) => {
            row.forEach((cell, x) => {
                const cellDiv = document.createElement('div');
                cellDiv.className = 'map-cell';
                
                // ================================================================
                // A CORREÇÃO ESTÁ AQUI (Dando um ID único)
                // ================================================================
                cellDiv.id = `cell-${y}-${x}`; // Ex: "cell-1-1"
                // ================================================================
                
                if (cell === 1) cellDiv.classList.add('path');
                if (cell === 'S') cellDiv.classList.add('start');
                if (cell === 'E') cellDiv.classList.add('end');
                
                const event_key = `${y},${x}`;
                const mapId = player.current_map_id;
                const event = mapData.events[event_key];
                
                const isCompleted = player.completed_events[mapId] && 
                                  player.completed_events[mapId][event_key];

                if (isCompleted) {
                    cellDiv.classList.add('completed');
                } else if (event) {
                    if (event.type === 'monster') {
                        cellDiv.classList.add('monster-event'); // ⚔️
                    } else {
                        cellDiv.classList.add('event'); // ?
                    }
                }
                
                mapGrid.appendChild(cellDiv);
            });
        });

        movePlayerAvatar(player.current_map_pos_x, player.current_map_pos_y);
    }
    
    function movePlayerAvatar(x, y) {
        const playerAvatar = document.getElementById('player-avatar');
        // O 'top' e 'left' agora são relativos ao 'mapGridContainer'
        playerAvatar.style.left = `${x * 40}px`;
        playerAvatar.style.top = `${y * 40}px`;
    }

    // --- TELA DE BATALHA ---
    function updateBattleScreen(state) {
        
        // 1. Reset o estado da UI para "Batalha Ativa"
        menuArea.style.display = 'block';
        gameOverArea.style.display = 'none';

        // 2. Atualiza JOGADOR
        playerNameText.innerText = state.player_name;
        playerHpBar.style.width = state.player_hp_percent + '%';
        playerHpText.innerText = state.player_hp + ' / ' + state.player_max_hp;
        playerSprite.style.backgroundImage = `url(${state.player_sprite})`;
        
        // --- ATUALIZAÇÃO DOS STATS DO JOGADOR ---
        playerStatAtk.innerText = state.player_stats.attack;
        playerStatDef.innerText = state.player_stats.defense;
        playerStatCrit.innerText = state.player_stats.crit_chance.toFixed(1); // ex: 10.5%
        
        // 3. Atualiza MONSTRO
        monsterNameText.innerText = state.monster_name;
        monsterHpBar.style.width = state.monster_hp_percent + '%';
        monsterHpText.innerText = state.monster_hp + ' / ' + state.monster_max_hp;
        monsterSprite.style.backgroundImage = `url(${state.monster_sprite})`;
        
        // --- ATUALIZAÇÃO DOS STATS DO MONSTRO ---
        monsterStatAtk.innerText = state.monster_stats.attack;
        monsterStatDef.innerText = state.monster_stats.defense;
        monsterStatCrit.innerText = state.monster_stats.crit_chance.toFixed(1); // ex: 5.0%

        // 4. Atualiza JOGO
        potionCountEl.innerText = state.potions;
        gameLogEl.innerText = state.log; // Este é o log de TURNO (ex: "Você atacou...")
        triggerAnimation(playerSprite, state.player_hit);
        triggerAnimation(monsterSprite, state.monster_hit);
        
        // 5. Ativa os botões de batalha
        toggleButtons(true);
    }

    // --- TELA DO MENU ---
    async function loadMainMenu() {
        showView('main-menu-view');
        
        try {
            const response = await fetch('api/game.php?action=check_saves');
            if (!response.ok) {
                // Tenta ler o erro como JSON
                const err = await response.json();
                throw new Error(err.error || 'Falha ao checar saves.');
            }
            
            const savedHeroes = await response.json();
            continueGameList.innerHTML = '';

            if (savedHeroes.length > 0) {
                savedHeroes.forEach(hero => {
                    const button = document.createElement('button');
                    button.className = 'hero-card continue-button';
                    button.dataset.heroId = hero.hero_id_key;
                    button.innerHTML = `<h3>${hero.class_name}</h3><p>Nível ${hero.level}</p>`;
                    continueGameList.appendChild(button);
                });
                
                continueGameSection.classList.remove('view-hidden');
                newGameTitle.innerText = 'Ou Criar Novo Herói:';

            } else {
                continueGameSection.classList.add('view-hidden');
                newGameTitle.innerText = 'Escolha seu Herói:';
            }

        } catch (error) {
            console.error('Erro ao carregar menu:', error);
            continueGameSection.classList.add('view-hidden');
            newGameTitle.innerText = 'Erro ao carregar. Tente novamente.';
        }
    }

    // --- Função para trocar de Tela ---
    function showView(viewId) {
        mainMenuView.classList.add('view-hidden');
        gameView.classList.add('view-hidden');
        mapView.classList.add('view-hidden');
        
        const viewToShow = document.getElementById(viewId);
        if (viewToShow) {
            viewToShow.classList.remove('view-hidden');
        }
    }
    
    // ===================================================================
    // 3. EVENT LISTENERS
    // ===================================================================

    // --- Lógica de Voltar ao Menu (da Batalha) ---
    returnToMenuBtn.addEventListener('click', () => {
        // ANTIGO (Errado):
        // loadMainMenu(); 
        
        // NOVO (Correto):
        // Nós assumimos que 'currentPlayerId' ainda está salvo
        // (desde quando o mapa foi carregado antes da batalha)
        if (currentPlayerId) {
            // Chama a API para recarregar o mapa
            sendAction('load_game', { hero_id: currentPlayerId });
        } else {
            // Se tudo falhar, volte ao menu principal
            loadMainMenu();
        }
    });

    // --- Lógica de Início do Jogo (Ouve cliques no menu) ---
    mainMenuView.addEventListener('click', (e) => {
        const card = e.target.closest('.hero-card');
        if (!card) return;
        
        const heroId = card.dataset.heroId;
        if (!heroId) return;

        if (card.classList.contains('continue-button')) {
            sendAction('load_game', { hero_id: heroId });
        } else {
            sendAction('start', { hero_id: heroId });
        }
    });

    // --- OUVINTE DOS CONTROLES DO MAPA ---
    mapControls.addEventListener('click', (e) => {
        const button = e.target.closest('.move-btn');
        if (!button) return;

        const direction = button.dataset.direction;
        sendAction('move', {
            hero_id: currentPlayerId,
            direction: direction
        });
    });

    // --- Escutador de Eventos de Batalha ---
    menuArea.addEventListener('click', (e) => {
        if (e.target.tagName === 'BUTTON' && e.target.hasAttribute('data-action')) {
            const action = e.target.getAttribute('data-action');
            sendAction(action, {});
        }
    });

    // ===================================================================
    // 4. FUNÇÃO CENTRAL DA API
    // ===================================================================
    
    async function sendAction(action, data = {}) {
        if (action === 'attack' || action === 'defend' || action === 'potion') {
            toggleButtons(false);
        }
        
        const url = `api/game.php?action=${action}`;
        const options = {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        };

        try {
            const response = await fetch(url, options);
            const apiResponse = await response.json();
            
            console.log(`Ação: ${action}`, `Resposta:`, apiResponse);

            handleApiResponse(apiResponse);

        } catch (error) {
            console.error("Erro de rede ou JSON inválido:", error);
            if (mapView.offsetParent !== null) { 
                mapLog.innerText = "Erro de conexão com a API.";
            } else {
                alert("Erro de conexão com a API.");
            }

            // ================================================================
            // CORREÇÃO (O Bloco CATCH)
            // ================================================================
            // Se qualquer chamada à API falhar, reative os controles
            // do mapa para evitar que o jogo trave.
            mapControls.style.pointerEvents = 'auto';
        }
    }
    
    // --- Funções Auxiliares de Batalha ---
    function toggleButtons(enable) {
        battleButtons.forEach(button => {
            button.disabled = !enable;
            button.style.opacity = enable ? '1' : '0.5';
        });
    }

    function triggerAnimation(element, wasHit) {
        element.classList.remove('hit-animation');
        if (wasHit) {
            void element.offsetWidth; 
            element.classList.add('hit-animation');
        }
    }
// ===================================================================
    // 5. OUVINTE DE COMANDOS DE TECLADO (Keybindings)
    // ===================================================================

    document.addEventListener('keydown', (e) => {
        // Pega a tecla pressionada (em minúsculas)
        const key = e.key.toLowerCase();

        // ---------------------------------------------
        // ROTA 1: Se o MAPA estiver visível
        // ---------------------------------------------
        if (!mapView.classList.contains('view-hidden')) {
            // Se os controles de movimento estiverem ativos
            if (mapControls.style.pointerEvents !== 'none' && currentPlayerId) {
                
                switch (key) {
                    case 'arrowup':
                    case 'w': // Adiciona 'W' como bônus
                        e.preventDefault(); // Impede a página de rolar
                        sendAction('move', { hero_id: currentPlayerId, direction: 'up' });
                        break;
                    case 'arrowdown':
                    case 's': // Adiciona 'S' como bônus
                        e.preventDefault();
                        sendAction('move', { hero_id: currentPlayerId, direction: 'down' });
                        break;
                    case 'arrowleft':
                    case 'a': // Conflito com 'A' de Atacar, mas aqui estamos no mapa
                        e.preventDefault();
                        sendAction('move', { hero_id: currentPlayerId, direction: 'left' });
                        break;
                    case 'arrowright':
                    case 'd': // Conflito com 'D' de Defender, mas aqui estamos no mapa
                        e.preventDefault();
                        sendAction('move', { hero_id: currentPlayerId, direction: 'right' });
                        break;
                }
            }
        }

        // ---------------------------------------------
        // ROTA 2: Se a BATALHA estiver visível
        // ---------------------------------------------
        // Verificamos se 'gameView' está visível E se os botões de ação (menuArea) estão visíveis
        else if (!gameView.classList.contains('view-hidden') && menuArea.style.display === 'block') {
            
            switch (key) {
                case 'a':
                    e.preventDefault(); // Impede o 'a' de ser digitado
                    sendAction('attack', {});
                    break;
                case 'd':
                    e.preventDefault();
                    sendAction('defend', {});
                    break;
                case ' ': // Tecla "Space"
                    e.preventDefault(); // Impede a página de rolar
                    sendAction('potion', {});
                    break;
            }
        }
    });

    // --- ESTADO INICIAL ---
    loadMainMenu();
}); 