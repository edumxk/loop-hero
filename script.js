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
    
    // --- Seletores de Batalha (NOVA ESTRUTURA) ---
    const returnToMenuBtn = document.getElementById('return-to-menu-btn');
    const menuArea = document.getElementById('menu-area');
    const gameOverArea = document.getElementById('game-over-area');
    const gameOverMessage = document.getElementById('game-over-message');
    
    // Botões
    const attackBtn = document.getElementById('attack-btn');
    const defendBtn = document.getElementById('defend-btn');
    const potionBtn = document.getElementById('potion-btn');
    const battleButtons = document.querySelectorAll('#menu-area button'); // Para toggle
    
    // HUD
    const battlePlayerName = document.getElementById('battle-player-name');
    const battlePlayerHpBar = document.getElementById('battle-player-hp-bar');
    const battlePlayerHpText = document.getElementById('battle-player-hp-text');
    const battleMonsterName = document.getElementById('battle-monster-name');
    const battleMonsterHpBar = document.getElementById('battle-monster-hp-bar');
    const battleMonsterHpText = document.getElementById('battle-monster-hp-text');
    const potionCountEl = document.getElementById('potion-count');
    const gameLogEl = document.getElementById('game-log'); // Log de batalha
    
    // Sprites
    const playerSprite = document.getElementById('player-sprite');
    const monsterSprite = document.getElementById('monster-sprite');

    // Stats
    const playerStatAtk = document.getElementById('player-stat-atk');
    const playerStatDef = document.getElementById('player-stat-def');
    const playerStatCrit = document.getElementById('player-stat-crit');
    const monsterStatAtk = document.getElementById('monster-stat-atk');
    const monsterStatDef = document.getElementById('monster-stat-def');
    const monsterStatCrit = document.getElementById('monster-stat-crit');


    // --- Seletores do Mapa ---
    const mapName = document.getElementById('map-name');
    const mapGridContainer = document.getElementById('map-grid-container');
    const mapGrid = document.getElementById('map-grid');
    const mapLog = document.getElementById('map-log');
    const mapControls = document.getElementById('map-controls');
    const mapHudGold = document.getElementById('map-hud-gold');
    const mapHudPotions = document.getElementById('map-hud-potions');
    const mapHudHp = document.getElementById('map-hud-hp');
    
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
            
            if(response.player && response.map_data) {
                currentPlayerId = response.player.hero_id_key;
                drawMapScreen(response.player, response.map_data);
            }
            if (response.player_pos) {
                movePlayerAvatar(response.player_pos.x, response.player_pos.y);
            }
            if (response.hud_update) {
                updateMapHud(response.hud_update);
            }
            if (response.log) {
                 mapLog.innerText = response.log;
            }

            if (response.event) {
                if (response.event.type === 'monster') {
                    mapLog.innerText = "Você encontrou um monstro! Preparando para a batalha...";
                    mapControls.style.pointerEvents = 'none';
                    
                    const difficulty = response.event.difficulty || 'easy';
                    const monsterId = response.event.monster_id || null;
                    
                    setTimeout(() => {
                        sendAction('trigger_battle', { 
                            hero_id: currentPlayerId,
                            difficulty: difficulty,
                            monster_id: monsterId
                        });
                    }, 1000); // 1-segundo de atraso

                }
                else if (response.event.type === 'trap' || response.event.type === 'treasure') {
                    const x = response.player_pos.x;
                    const y = response.player_pos.y;
                    const cellToUpdate = document.getElementById(`cell-${y}-${x}`);
                    
                    if (cellToUpdate) {
                        cellToUpdate.classList.remove('event', 'monster-event');
                        cellToUpdate.classList.add('completed');
                    }
                    mapControls.style.pointerEvents = 'auto';
                }
                
            } else {
                mapControls.style.pointerEvents = 'auto';
            }

            showView('map-view');

        } else if (response.view === 'battle') {
            mapControls.style.pointerEvents = 'auto';
            updateBattleScreen(response.battle_data);
            showView('game-view');

        } else if (response.view === 'battle_over') {
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

    function drawMapScreen(player, mapData) {
        mapName.innerText = mapData.name;
        
        updateMapHud({
            gold: player.gold,
            potions: player.potions,
            hp: player.hp,
            max_hp: player.base_stats.max_hp
        });
        
        mapGrid.innerHTML = '';
        mapGrid.style.gridTemplateColumns = `repeat(${mapData.map[0].length}, 40px)`;
        
        let playerAvatar = document.getElementById('player-avatar');
        if (!playerAvatar) {
            playerAvatar = document.createElement('div');
            playerAvatar.id = 'player-avatar';
            mapGridContainer.appendChild(playerAvatar);
        }

        mapData.map.forEach((row, y) => {
            row.forEach((cell, x) => {
                const cellDiv = document.createElement('div');
                cellDiv.className = 'map-cell';
                cellDiv.id = `cell-${y}-${x}`;
                
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
                        cellDiv.classList.add('monster-event');
                    } else {
                        cellDiv.classList.add('event');
                    }
                }
                
                mapGrid.appendChild(cellDiv);
            });
        });

        movePlayerAvatar(player.current_map_pos_x, player.current_map_pos_y);
    }
    
    function movePlayerAvatar(x, y) {
        const playerAvatar = document.getElementById('player-avatar');
        playerAvatar.style.left = `${x * 40}px`;
        playerAvatar.style.top = `${y * 40}px`;
    }

    // --- TELA DE BATALHA (REESCRITA) ---
    function updateBattleScreen(state) {
        
        // 1. Reset o estado da UI para "Batalha Ativa"
        menuArea.style.display = 'flex'; // 'flex' em vez de 'block'
        gameOverArea.style.display = 'none';

        // 2. Atualiza JOGADOR
        battlePlayerName.innerText = state.player_name;
        battlePlayerHpBar.style.width = state.player_hp_percent + '%';
        battlePlayerHpText.innerText = state.player_hp + ' / ' + state.player_max_hp;
        // !! IMPORTANTE: Certifique-se que o 'state.player_sprite' vem da API
        //    (O game.php já faz isso, usando 'assets/heroes/human_knight.png')
        playerSprite.style.backgroundImage = `url(${state.player_sprite})`;
        
        // Stats do Jogador
        playerStatAtk.innerText = state.player_stats.attack;
        playerStatDef.innerText = state.player_stats.defense;
        playerStatCrit.innerText = state.player_stats.crit_chance.toFixed(1);
        
        // 3. Atualiza MONSTRO
        battleMonsterName.innerText = state.monster_name;
        battleMonsterHpBar.style.width = state.monster_hp_percent + '%';
        battleMonsterHpText.innerText = state.monster_hp + ' / ' + state.monster_max_hp;
        monsterSprite.style.backgroundImage = `url(${state.monster_sprite})`;
        
        // Stats do Monstro
        monsterStatAtk.innerText = state.monster_stats.attack;
        monsterStatDef.innerText = state.monster_stats.defense;
        monsterStatCrit.innerText = state.monster_stats.crit_chance.toFixed(1);

        // 4. Atualiza JOGO
        potionCountEl.innerText = state.potions;
        gameLogEl.innerText = state.log;
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
        // Se o currentPlayerId foi limpo (pela permadeath), volta ao menu.
        if (currentPlayerId) {
            sendAction('load_game', { hero_id: currentPlayerId });
        } else {
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

    // --- Escutador de Eventos de Batalha (Botões) ---
    attackBtn.addEventListener('click', () => sendAction('attack', {}));
    defendBtn.addEventListener('click', () => sendAction('defend', {}));
    potionBtn.addEventListener('click', () => sendAction('potion', {}));

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
            mapControls.style.pointerEvents = 'auto';
        }
    }
    
    // --- Funções Auxiliares de Batalha ---
    function toggleButtons(enable) {
        battleButtons.forEach(button => {
            // A poção tem sua própria lógica
            if (button.id === 'potion-btn') return; 
            
            button.disabled = !enable;
            button.style.opacity = enable ? '1' : '0.5';
        });
        
        // Lógica separada para o botão de poção
        // (Só ativa se for 'enable' E se o 'potionCount' for > 0)
        const potionCount = parseInt(potionCountEl.innerText) || 0;
        potionBtn.disabled = !enable || potionCount === 0;
        potionBtn.style.opacity = potionBtn.disabled ? '0.5' : '1';
    }

    function triggerAnimation(element, wasHit) {
        element.classList.remove('hit-animation');
        // Precisamos checar qual elemento é para usar a animação correta
        if (element.id === 'monster-sprite') {
            element.classList.remove('hit-animation-player'); // Remove a outra
            if (wasHit) {
                void element.offsetWidth; 
                element.classList.add('hit-animation'); // Animação do monstro (com scaleX)
            }
        } else {
            element.classList.remove('hit-animation'); // Remove a outra
            if (wasHit) {
                void element.offsetWidth;
                element.classList.add('hit-animation-player'); // Animação do jogador (sem scaleX)
            }
        }
    }
    
    // ===================================================================
    // 5. OUVINTE DE COMANDOS DE TECLADO (Keybindings)
    // ===================================================================

    document.addEventListener('keydown', (e) => {
        const key = e.key.toLowerCase();

        // ROTA 1: Se o MAPA estiver visível
        if (!mapView.classList.contains('view-hidden')) {
            if (mapControls.style.pointerEvents !== 'none' && currentPlayerId) {
                switch (key) {
                    case 'arrowup':
                    case 'w':
                        e.preventDefault();
                        sendAction('move', { hero_id: currentPlayerId, direction: 'up' });
                        break;
                    case 'arrowdown':
                    case 's':
                        e.preventDefault();
                        sendAction('move', { hero_id: currentPlayerId, direction: 'down' });
                        break;
                    case 'arrowleft':
                    case 'a':
                        e.preventDefault();
                        sendAction('move', { hero_id: currentPlayerId, direction: 'left' });
                        break;
                    case 'arrowright':
                    case 'd':
                        e.preventDefault();
                        sendAction('move', { hero_id: currentPlayerId, direction: 'right' });
                        break;
                }
            }
        }

        // ROTA 2: Se a BATALHA estiver visível
        else if (!gameView.classList.contains('view-hidden') && menuArea.style.display !== 'none') {
            switch (key) {
                case 'a':
                    e.preventDefault();
                    if (!attackBtn.disabled) sendAction('attack', {});
                    break;
                case 'd':
                    e.preventDefault();
                    if (!defendBtn.disabled) sendAction('defend', {});
                    break;
                case ' ': // Tecla "Space"
                    e.preventDefault();
                    if (!potionBtn.disabled) sendAction('potion', {});
                    break;
            }
        }
    });

    // --- ESTADO INICIAL ---
    loadMainMenu();
});