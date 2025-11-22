document.addEventListener('DOMContentLoaded', () => {

    // --- Seletores de View ---
    const titleScreen = document.getElementById('title-screen');
    const prologueView = document.getElementById('prologue-view');
    const heroSelectionView = document.getElementById('hero-selection-view');
    const gameView = document.getElementById('game-view');
    const mapView = document.getElementById('map-view');
    const loadingOverlay = document.getElementById('loading-overlay'); 

    // --- Variáveis de Estado ---
    let currentPlayerId = null;
    let isProcessingAction = false; 
    let battleInterval = null;
    let gameSpeed = 1; // <--- NOVO: Velocidade padrão
    let prologueTimer = null; // <--- NOVO: Para controlar o tempo do texto
    
    let currentHeroFolder = ''; 
    let currentMonsterFolder = '';
    let isPlayerAnimationPlaying = false; 
    let isMonsterAnimationPlaying = false;
    
    const actionsList = ['idle', 'attack', 'defence', 'cure', 'hit', 'dead'];

    // --- Seletores do Menu ---
    const btnNewGame = document.getElementById('btn-new-game');
    const savedGamesList = document.getElementById('saved-games-list'); // <--- NOVO
    const startScreen = document.getElementById('start-screen'); // <--- NOVO

    //const btnContinueGame = document.getElementById('btn-continue-game');
    const btnEndPrologue = document.getElementById('btn-end-prologue');
    const btnSkipPrologue = document.getElementById('btn-skip-prologue'); // <--- NOVO
    const btnBackTitle = document.getElementById('btn-back-title');
    const heroCards = document.querySelectorAll('.hero-card');
    const prologueAudio = document.getElementById('prologue-audio');
    const menuAudio = document.getElementById('menu-audio');
    
    // --- Seletores Batalha e Mapa ---
    const returnToMenuBtn = document.getElementById('return-to-menu-btn');
    const menuArea = document.getElementById('menu-area');
    const gameOverArea = document.getElementById('game-over-area');
    const gameOverMessage = document.getElementById('game-over-message');
    
    // Botões de Ação (Corrigidos)
    const attackBtn = document.getElementById('attack-btn');
    const defendBtn = document.getElementById('defend-btn');
    const potionBtn = document.getElementById('potion-btn');
    const battleButtons = document.querySelectorAll('#menu-area button');
    const speedButtons = document.querySelectorAll('.speed-btn'); // <--- NOVO
    
    const battlePlayerName = document.getElementById('battle-player-name');
    const battlePlayerHpBar = document.getElementById('battle-player-hp-bar');
    const battlePlayerHpText = document.getElementById('battle-player-hp-text');
    const battleMonsterName = document.getElementById('battle-monster-name');
    const battleMonsterHpBar = document.getElementById('battle-monster-hp-bar');
    const battleMonsterHpText = document.getElementById('battle-monster-hp-text');
    const potionCountEl = document.getElementById('potion-count');
    const gameLogEl = document.getElementById('game-log');
    
    const battlePlayerAtbBar = document.getElementById('battle-player-atb-bar');
    const battleMonsterAtbBar = document.getElementById('battle-monster-atb-bar');

    const playerStatAtk = document.getElementById('player-stat-atk');
    const playerStatDef = document.getElementById('player-stat-def');
    const playerStatCrit = document.getElementById('player-stat-crit');
    const monsterStatAtk = document.getElementById('monster-stat-atk');
    const monsterStatDef = document.getElementById('monster-stat-def');
    const monsterStatCrit = document.getElementById('monster-stat-crit');

    const mapName = document.getElementById('map-name');
    const mapGrid = document.getElementById('map-grid');
    const mapLog = document.getElementById('map-log');
    const mapControls = document.getElementById('map-controls');
    const mapHudGold = document.getElementById('map-hud-gold');
    const mapHudPotions = document.getElementById('map-hud-potions');
    const mapHudHp = document.getElementById('map-hud-hp');

    // ===================================================================
    // 1. LÓGICA DE MENU E NAVEGAÇÃO
    // ===================================================================

    //checkSaves();
    

    showView('start-screen');

    function initGameAudio() {
        // 1. Toca a música do menu
        if (menuAudio) {
            menuAudio.volume = 0.4; // Volume agradável
            menuAudio.play().catch(e => console.log("Áudio bloqueado:", e));
        }

        // 2. Remove listener para não disparar dnv
        document.removeEventListener('click', initGameAudio);
        document.removeEventListener('keydown', initGameAudio);

        // 3. Carrega os saves e vai para o menu real
        checkSaves(); 
        hideStartScreenWithFade();
    }

    // Adiciona listeners globais para o primeiro clique/tecla
    // Usamos 'startScreen' click ou document keydown
    startScreen.addEventListener('click', initGameAudio);
    document.addEventListener('keydown', (e) => {
        // Só ativa se a tela de start estiver visível
        if (!startScreen.classList.contains('view-hidden')) {
            initGameAudio();
        }
    });

    function hideStartScreenWithFade() {
        const startScreen = document.getElementById('start-screen');
        
        // 1. Impede novos cliques durante a animação
        startScreen.style.pointerEvents = 'none'; 
        
        // 2. Inicia o Fade (CSS cuida da animação de 2s)
        startScreen.style.opacity = '0';

        // 3. Espera 2 segundos (2000ms) para remover do fluxo da página
        setTimeout(() => {
            startScreen.classList.add('view-hidden');
            
            // Chama a função de carregar saves (mostra o menu) APÓS o fade
            checkSaves(); 
        }, 2000);
    }

    async function checkSaves() {
        showView('title-screen');
        try {
            const response = await fetch('api/game.php?action=check_saves');
            if (response.ok) {
                const saves = await response.json();
                
                // Limpa a lista atual
                savedGamesList.innerHTML = '';

                if (saves && saves.length > 0) {
                    // Cria um botão para CADA save encontrado
                    saves.forEach(save => {
                        const btn = document.createElement('button');
                        btn.className = 'saved-game-btn'; // Usa a nova classe CSS menor
                        
                        // Formatação do texto: "Nome do Herói      Lvl X"
                        btn.innerHTML = `
                            <span>${save.class_name}</span>
                            <small>Lvl ${save.level}</small>
                        `;
                        
                        // Adiciona o evento de clique para carregar este herói específico
                        btn.addEventListener('click', () => {
                            sendAction('load_game', { hero_id: save.hero_id_key });
                        });

                        savedGamesList.appendChild(btn);
                    });
                    
                    // Adiciona um título ou separador visual se quiser (opcional)
                    savedGamesList.style.display = 'flex';
                } else {
                    savedGamesList.style.display = 'none';
                }
            }
        } catch (e) { 
            console.error("Erro ao checar saves", e); 
        }
        menuAudio.play().catch(e => console.warn("Áudio do menu bloqueado pelo navegador", e));
    }

    function stopPrologueAudio() {
        if (prologueAudio) {
            prologueAudio.pause();
            prologueAudio.currentTime = 0; // Reseta para o início
        }
    }

    function showView(viewId) {
        const views = [titleScreen, prologueView, heroSelectionView, gameView, mapView];
        views.forEach(v => v.classList.add('view-hidden'));
        document.getElementById(viewId).classList.remove('view-hidden');
    }

    // Navegação
    btnEndPrologue.addEventListener('click', () => showView('hero-selection-view'));
    btnSkipPrologue.addEventListener('click', () => {
        // 1. Cancela o timer natural (já que vamos pular)
        if (prologueTimer) clearTimeout(prologueTimer);
        stopPrologueAudio();
        // 2. Força todo o texto a aparecer imediatamente
        const storyContainer = document.querySelector('.story-container');
        storyContainer.classList.add('fast-forward');

        // 3. Troca os botões
        btnSkipPrologue.style.display = 'none'; // Esconde botão de pular
        
        const btnEnd = document.getElementById('btn-end-prologue');
        btnEnd.style.display = 'inline-block'; // Torna visível e clicável
        btnEnd.style.opacity = '1'; // Garante opacidade total
        btnEnd.style.animation = 'none'; // Remove animação lenta para aparecer na hora
    });

    btnBackTitle.addEventListener('click', () => showView('title-screen'));

    returnToMenuBtn.addEventListener('click', () => {
        if (currentPlayerId) sendAction('load_game', { hero_id: currentPlayerId });
        else checkSaves();
    });

    btnNewGame.addEventListener('click', () => {
        showView('prologue-view');
        
        // --- NOVO: TOCA O ÁUDIO ---
        if (prologueAudio) {
            prologueAudio.volume = 1; 
            prologueAudio.play().catch(e => console.warn("Áudio bloqueado pelo navegador", e));
        }
        // --------------------------
        // 1. Reseta o estado visual do prólogo
        const storyContainer = document.querySelector('.story-container');
        storyContainer.classList.remove('fast-forward');
        
        // 2. Botões: Mostra o Skip, Esconde o Final
        btnSkipPrologue.style.display = 'block';
        const btnEnd = document.getElementById('btn-end-prologue');
        btnEnd.style.display = 'none'; // Garante que não dá pra clicar
        btnEnd.classList.remove('fade-in-btn');
        btnEnd.style.opacity = '0'; 

        // 3. Inicia o Timer Natural (11 segundos = tempo das animações CSS)
        if (prologueTimer) clearTimeout(prologueTimer);
        
        prologueTimer = setTimeout(() => {
            // Se o usuário esperou tudo:
            btnSkipPrologue.style.display = 'none'; // Esconde o Skip
            btnEnd.classList.add('fade-in-btn');    // Mostra o Final suavemente
        }, 22000); // 11s é a soma dos delays do CSS
    });

    // Cards de Herói
    heroCards.forEach(card => {
        const img = card.querySelector('img');
        const folder = card.dataset.folder;

        card.addEventListener('mouseenter', () => {
            img.src = `${folder}attack.png`;
        });
        card.addEventListener('mouseleave', () => {
            img.src = `${folder}idle.png`;
        });

        card.addEventListener('click', () => {
            const heroId = card.dataset.heroId;
            sendAction('start', { hero_id: heroId });
        });
    });


    // ===================================================================
    // 2. LÓGICA DE SPRITES (BATALHA)
    // ===================================================================
    
    function setupSprites(role, folderPath) {
        return new Promise((resolve) => {
            const containerId = `${role}-sprite`;
            const container = document.getElementById(containerId);
            container.innerHTML = ''; 
            
            let imagesLoaded = 0;
            const totalImages = actionsList.length; // idle, attack, hit, etc.

            // Função auxiliar para checar se acabou
            const checkLoad = () => {
                imagesLoaded++;
                if (imagesLoaded >= totalImages) {
                    console.log(`Todos os sprites de ${role} carregados.`);
                    resolve(); // Libera o loading
                }
            };

            actionsList.forEach(action => {
                const img = document.createElement('img');
                img.src = `${folderPath}${action}.png`;
                img.id = `${role}-img-${action}`;
                img.className = `${role}-sprite-img`;
                
                // Estilos iniciais
                img.style.display = 'none';
                img.style.position = 'absolute';
                img.style.top = '0';
                img.style.left = '0';
                img.style.width = '100%';
                img.style.height = '100%';
                
                if (action === 'idle') img.style.display = 'block';

                // --- LISTENERS DE CARREGAMENTO ---
                img.onload = checkLoad; 
                img.onerror = () => {
                    console.warn(`Imagem falhou ou não existe: ${img.src}`);
                    checkLoad(); // Conta como carregado para não travar o jogo
                };

                container.appendChild(img);
            });
        });
    }

    function setSprite(role, action, duration = 0) {
        if (role === 'player' && !currentHeroFolder) return;
        if (role === 'monster' && !currentMonsterFolder) return;

        if (duration > 0) {
            if (role === 'player') isPlayerAnimationPlaying = true;
            if (role === 'monster') isMonsterAnimationPlaying = true;
        }

        const containerId = `${role}-sprite`;
        const container = document.getElementById(containerId);
        if (!container) return;

        const images = container.querySelectorAll('img');
        images.forEach(img => img.style.display = 'none');

        const targetId = `${role}-img-${action}`;
        const targetImg = document.getElementById(targetId);
        
        if (targetImg) {
            targetImg.style.display = 'block';
        } else {
            const idleImg = document.getElementById(`${role}-img-idle`);
            if (idleImg) idleImg.style.display = 'block';
        }

        if (duration > 0) {
            setTimeout(() => {
                if (role === 'player') isPlayerAnimationPlaying = false;
                if (role === 'monster') isMonsterAnimationPlaying = false;
            }, duration);
        }
    }


    // ===================================================================
    // 3. LOOP DE BATALHA (ATB)
    // ===================================================================
    
    function startBattleLoop() {
        if (battleInterval) clearInterval(battleInterval);

        battleInterval = setInterval(() => {
            const isGameOver = gameOverArea.style.display !== 'none';
            if (!isProcessingAction && !isGameOver) {
                sendAction('tick', { multiplier: gameSpeed });
            }
        }, 1000);
    }

    function stopBattleLoop() {
        if (battleInterval) {
            clearInterval(battleInterval);
            battleInterval = null;
        }
    }

    speedButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            // 1. Atualiza variável visual
            speedButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            // 2. Atualiza valor lógico
            gameSpeed = parseInt(btn.dataset.speed);
            
            // 3. Reinicia o loop IMEDIATAMENTE com a nova velocidade
            // (Só reinicia se já estivermos em batalha, senão o loop começa errado)
            if (!gameView.classList.contains('view-hidden')) {
                startBattleLoop();
            }
        });
    });
    
    // ===================================================================
    // 4. COMUNICAÇÃO COM API
    // ===================================================================
    
    async function sendAction(action, data = {}) {
        if (isProcessingAction) return;
        isProcessingAction = true;

        // Animações Imediatas
        if (action === 'attack') setSprite('player', 'attack', 800); 
        else if (action === 'potion') setSprite('player', 'cure', 800);   
        else if (action === 'defend') setSprite('player', 'defence', 0);  

        if (['attack', 'defend', 'potion'].includes(action)) toggleButtons(false);
        if (action === 'move') mapControls.style.pointerEvents = 'none';

        const url = `api/game.php?action=${action}`;
        const options = {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        };

        try {
            const response = await fetch(url, options);
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Erro Servidor (${response.status}): ${errorText}`);
            }
            const apiResponse = await response.json();
            if (action !== 'tick') console.log(action, apiResponse);
            handleApiResponse(apiResponse);

        } catch (error) {
            console.error("ERRO:", error);
            if (mapView.offsetParent !== null) mapLog.innerText = "Erro conexão.";
            else alert("Erro de conexão: " + error.message);
            
            mapControls.style.pointerEvents = 'auto';
            if (!gameView.classList.contains('view-hidden')) toggleButtons(true);
        } finally {
            isProcessingAction = false;
        }
    }

    function handleApiResponse(response) {
        if (response.error) {
            if (response.error !== 'Aguarde sua barra de ação encher!') console.error(response.error);
            mapControls.style.pointerEvents = 'auto';
            return;
        }

        if (response.view === 'map') {
            stopBattleLoop();
            if(response.player && response.map_data) {
                currentPlayerId = response.player.hero_id_key;
                drawMapScreen(response.player, response.map_data);
            }
            if (response.player_pos) movePlayerAvatar(response.player_pos.x, response.player_pos.y);
            if (response.hud_update) updateMapHud(response.hud_update);
            if (response.log) mapLog.innerText = response.log;

            if (response.event) {
                if (response.event.type === 'monster') {
                    mapLog.innerText = "Monstro! Batalha em 1s...";
                    mapControls.style.pointerEvents = 'none';
                    setTimeout(() => {
                        sendAction('trigger_battle', { 
                            hero_id: currentPlayerId,
                            difficulty: response.event.difficulty || 'easy',
                            monster_id: response.event.monster_id || null
                        });
                    }, 1000);
                } else if (['trap', 'treasure'].includes(response.event.type)) {
                    const cell = document.getElementById(`cell-${response.player_pos.y}-${response.player_pos.x}`);
                    if (cell) {
                        cell.classList.remove('event', 'monster-event');
                        cell.classList.add('completed');
                    }
                    mapControls.style.pointerEvents = 'auto';
                }
            } else {
                mapControls.style.pointerEvents = 'auto';
            }
            showView('map-view');

        } else if (response.view === 'battle') {
            mapControls.style.pointerEvents = 'auto';
            
            // Verifica se estamos ENTRANDO na batalha agora (vindo do mapa ou menu)
            // Se o gameView estava escondido, significa que é o início da luta.
            const isBattleStart = gameView.classList.contains('view-hidden');

            if (isBattleStart) {
                // 1. Mostra Loading
                loadingOverlay.classList.remove('view-hidden');
                
                // 2. Configura os sprites e ESPERA (await) carregar
                // Nota: Precisamos que handleApiResponse seja 'async' para usar await, 
                // ou usamos .then(). Vamos usar .then() para manter compatibilidade fácil.
                
                const p1 = setupSprites('player', response.battle_data.player_sprite_folder);
                const p2 = setupSprites('monster', response.battle_data.monster_sprite_folder);

                // Atualiza as variáveis globais de pasta para controle futuro
                currentHeroFolder = response.battle_data.player_sprite_folder;
                currentMonsterFolder = response.battle_data.monster_sprite_folder;

                Promise.all([p1, p2]).then(() => {
                    // 3. Quando tudo carregar:
                    updateBattleScreen(response.battle_data);
                    showView('game-view');
                    loadingOverlay.classList.add('view-hidden'); // Esconde Loading
                    if (!battleInterval) startBattleLoop();
                });

            } else {
                // Se já estamos na batalha (é apenas um Tick ou Ataque), não mostra loading
                updateBattleScreen(response.battle_data);
                // O showView aqui é redundante mas seguro
                // showView('game-view'); 
            }

        } else if (response.view === 'battle_over') {
            stopBattleLoop();
            if (response.battle_data) updateBattleScreen(response.battle_data);
            
            showView('game-view'); 
            menuArea.style.display = 'none';
            gameOverMessage.innerText = response.log;
            gameOverArea.style.display = 'block';
            
            if (response.hero_id === null) currentPlayerId = null;
        }
    }

    // ===================================================================
    // 5. DESENHO DE TELA (MAPA E BATALHA)
    // ===================================================================

    function updateMapHud(hud) {
        if (hud.gold !== undefined) mapHudGold.innerText = hud.gold;
        if (hud.potions !== undefined) mapHudPotions.innerText = hud.potions;
        if (hud.hp !== undefined) mapHudHp.innerText = `${hud.hp} / ${hud.max_hp}`;
    }

    function drawMapScreen(player, mapData) {
        mapName.innerText = mapData.name;
        updateMapHud({ gold: player.gold, potions: player.potions, hp: player.hp, max_hp: player.base_stats.max_hp });
        
        mapGrid.innerHTML = '';
        mapGrid.style.gridTemplateColumns = `repeat(${mapData.map[0].length}, 40px)`;        
        
        let playerAvatar = document.createElement('div');
        playerAvatar.id = 'player-avatar';
        mapGrid.appendChild(playerAvatar);

        mapData.map.forEach((row, y) => {
            row.forEach((cell, x) => {
                const cellDiv = document.createElement('div');
                cellDiv.className = 'map-cell';
                cellDiv.id = `cell-${y}-${x}`;
                if (cell === 1) cellDiv.classList.add('path');
                if (cell === 'S') cellDiv.classList.add('start');
                if (cell === 'E') cellDiv.classList.add('end');
                
                const eventKey = `${y},${x}`;
                const mapId = player.current_map_id;
                const event = mapData.events[eventKey];
                const isCompleted = player.completed_events[mapId] && player.completed_events[mapId][eventKey];

                if (isCompleted) cellDiv.classList.add('completed');
                else if (event) {
                    if (event.type === 'monster') cellDiv.classList.add('monster-event');
                    else cellDiv.classList.add('event');
                }
                mapGrid.appendChild(cellDiv);
            });
        });
        movePlayerAvatar(player.current_map_pos_x, player.current_map_pos_y);
    }
    
    function movePlayerAvatar(x, y) {
        const av = document.getElementById('player-avatar');
        if(av) { av.style.left = `${x*40}px`; av.style.top = `${y*40}px`; }
    }

    function updateBattleScreen(state) {
        menuArea.style.display = 'flex';
        gameOverArea.style.display = 'none';

        // 1. Configuração Inicial
        if (currentHeroFolder !== state.player_sprite_folder) {
            currentHeroFolder = state.player_sprite_folder;
            setupSprites('player', currentHeroFolder);
        }
        if (currentMonsterFolder !== state.monster_sprite_folder) {
            currentMonsterFolder = state.monster_sprite_folder;
            setupSprites('monster', currentMonsterFolder);
        }

        // 2. Escala
        const baseSize = 300; 
        const playerContainer = document.getElementById('player-sprite');
        const monsterContainer = document.getElementById('monster-sprite');
        
        const pSize = baseSize * (state.player_scale || 1.0);
        const mSize = baseSize * (state.monster_scale || 1.0);
        
        if(playerContainer) { playerContainer.style.width = `${pSize}px`; playerContainer.style.height = `${pSize}px`; }
        if(monsterContainer) { monsterContainer.style.width = `${mSize}px`; monsterContainer.style.height = `${mSize}px`; }

        // 3. HUD Player
        battlePlayerName.innerText = state.player_name;
        battlePlayerHpBar.style.width = state.player_hp_percent + '%';
        battlePlayerHpText.innerText = `${state.player_hp} / ${state.player_max_hp}`;
        
        playerStatAtk.innerText = state.player_stats.attack;
        // Usa o 'defense_display' que já vem somado com +10 se tiver buff
        playerStatDef.innerText = state.player_stats.defense_display; 
        // Adiciona um indicador visual se estiver buffado
        if (state.player_def_stacks > 0) {
            playerStatDef.style.color = '#3498db'; // Azul para indicar buff
            playerStatDef.innerText += ` (🛡️${state.player_def_stacks})`;
        } else {
            playerStatDef.style.color = '#f0f0f0';
        }
        playerStatCrit.innerText = state.player_stats.crit_chance.toFixed(1);

        // 4. Sprite Player (Lógica de Stacks)
        if (state.player_hp <= 0) {
            setSprite('player', 'dead', 0);
            isPlayerAnimationPlaying = false;
        } else if (state.player_hit) {
            setSprite('player', 'hit', 500);
            if(playerContainer) {
                playerContainer.classList.remove('hit-animation'); void playerContainer.offsetWidth; playerContainer.classList.add('hit-animation');
            }
        } else if (!isPlayerAnimationPlaying) {
             // MUDANÇA AQUI: Se tiver stacks, fica em posição de defesa
             if (state.player_def_stacks > 0) setSprite('player', 'defence', 0);
             else setSprite('player', 'idle', 0);
        }

        // 5. HUD Monstro
        battleMonsterName.innerText = state.monster_name;
        battleMonsterHpBar.style.width = state.monster_hp_percent + '%';
        battleMonsterHpText.innerText = `${state.monster_hp} / ${state.monster_max_hp}`;
        
        monsterStatAtk.innerText = state.monster_stats.attack;
        monsterStatDef.innerText = state.monster_stats.defense_display;
        // Indicador visual para o monstro também
        if (state.monster_def_stacks > 0) {
            monsterStatDef.style.color = '#c0392b'; // Vermelho/Laranja
            monsterStatDef.innerText += ` (🛡️${state.monster_def_stacks})`;
        } else {
            monsterStatDef.style.color = '#f0f0f0';
        }
        monsterStatCrit.innerText = state.monster_stats.crit_chance.toFixed(1);

        // 6. Sprite Monstro
        if (state.monster_hp <= 0) {
            setSprite('monster', 'dead', 0);
            isMonsterAnimationPlaying = false;
        } else if (state.monster_hit) {
            setSprite('monster', 'hit', 500);
            if(monsterContainer) {
                monsterContainer.classList.remove('hit-animation'); void monsterContainer.offsetWidth; monsterContainer.classList.add('hit-animation');
            }
        } else if (state.player_hit) {
            setSprite('monster', 'attack', 500);
        } else if (!isMonsterAnimationPlaying) {
            // Opcional: Se quiser sprite de defesa para monstro, use a lógica abaixo:
            // if (state.monster_def_stacks > 0) setSprite('monster', 'defence', 0); else ...
            setSprite('monster', 'idle', 0);
        }

        // 7. ATB e Logs
        if (state.meters) {
            if (battlePlayerAtbBar) {
                battlePlayerAtbBar.style.width = `${state.meters.player}%`;
                state.meters.player >= 100 ? battlePlayerAtbBar.classList.add('ready') : battlePlayerAtbBar.classList.remove('ready');
            }
            if (battleMonsterAtbBar) battleMonsterAtbBar.style.width = `${state.meters.monster}%`;
        }

        potionCountEl.innerText = state.potions;
        gameLogEl.innerText = state.log;
        
        // ATUALIZAÇÃO VISUAL DO COOLDOWN DE DEFESA
        if (state.player_def_cd > 0) {
            defendBtn.innerText = `Recarga (${state.player_def_cd})`;
            defendBtn.dataset.cooldown = "true"; // Marcador para lógica
        } else {
            defendBtn.innerText = `Defender (D)`;
            defendBtn.dataset.cooldown = "false";
        }

        const isGameOver = state.game_over; 
        const playerCanAct = (state.meters && state.meters.player >= 100) && !isGameOver;
        toggleButtons(playerCanAct);
    }

    function toggleButtons(enable) {
        // Botão de Ataque (Sempre habilitado se for turno)
        attackBtn.disabled = !enable;
        attackBtn.style.opacity = enable ? '1' : '0.5';

        // Botão de Defesa (Habilitado se turno E sem cooldown)
        const isDefCoolingDown = defendBtn.dataset.cooldown === "true";
        defendBtn.disabled = !enable || isDefCoolingDown;
        defendBtn.style.opacity = (!enable || isDefCoolingDown) ? '0.5' : '1';

        // Botão de Poção (Habilitado se turno E tiver poção)
        const pots = parseInt(potionCountEl.innerText) || 0;
        potionBtn.disabled = !enable || pots === 0;
        potionBtn.style.opacity = (!enable || pots === 0) ? '0.5' : '1';
    }

    // ===================================================================
    // 6. EVENT LISTENERS (A PARTE QUE FALTAVA!)
    // ===================================================================
    
    // Listeners de Clique dos Botões de Batalha
    attackBtn.addEventListener('click', () => sendAction('attack', {}));
    defendBtn.addEventListener('click', () => sendAction('defend', {}));
    potionBtn.addEventListener('click', () => sendAction('potion', {}));

    // Listeners de Movimento do Mapa
    mapControls.addEventListener('click', (e) => {
        const button = e.target.closest('.move-btn');
        if (button) sendAction('move', { hero_id: currentPlayerId, direction: button.dataset.direction });
    });


    // --- TECLADO ---
    document.addEventListener('keydown', (e) => {
        const key = e.key.toLowerCase();
        // Mapa
        if (!mapView.classList.contains('view-hidden')) {
            if (mapControls.style.pointerEvents !== 'none' && currentPlayerId && !isProcessingAction) {
                if(['arrowup','w'].includes(key)) sendAction('move', { hero_id: currentPlayerId, direction: 'up' });
                if(['arrowdown','s'].includes(key)) sendAction('move', { hero_id: currentPlayerId, direction: 'down' });
                if(['arrowleft','a'].includes(key)) sendAction('move', { hero_id: currentPlayerId, direction: 'left' });
                if(['arrowright','d'].includes(key)) sendAction('move', { hero_id: currentPlayerId, direction: 'right' });
            }
        }
        // Batalha
        else if (!gameView.classList.contains('view-hidden') && menuArea.style.display !== 'none') {
            if (isProcessingAction) return; 
            if (key === 'a' && !attackBtn.disabled) sendAction('attack', {});
            if (key === 'd' && !defendBtn.disabled) sendAction('defend', {});
            if (key === ' ' && !potionBtn.disabled) sendAction('potion', {});
        }
    });

});