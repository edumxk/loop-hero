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
    let playerDataCache = null; // Para armazenar dados do jogador
    let lastPlayerHp = null;
    let lastMonsterHp = null;

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

    const shopModal = document.getElementById('shop-modal');
    const btnCloseShop = document.getElementById('btn-close-shop');
    const shopBuyButtons = document.querySelectorAll('.btn-buy');

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
    function preloadImage(url) {
        return new Promise((resolve) => {
            const img = new Image();
            img.src = url;
            img.onload = () => resolve(url);
            img.onerror = () => {
                console.warn(`Erro ao carregar imagem: ${url}`);
                resolve(null); // Resolve mesmo com erro para não travar o jogo
            };
        });
    }

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
                    setTimeout(() => {
                        resolve(); // Libera o loading
                    }, 2000); // Pequeno delay para garantir renderização
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

    function showDamageText(targetId, amount, type = 'normal') {
        const container = document.getElementById(targetId); // 'player-sprite' ou 'monster-sprite'
        if (!container) return;

        const text = document.createElement('div');
        text.className = 'damage-text';

        // Configura o conteúdo e estilo
        if (type === 'crit') {
            text.innerText = amount + "!";
            text.classList.add('crit');
        } else if (type === 'heal') {
            text.innerText = "+" + amount;
            text.classList.add('heal');
        } else {
            text.innerText = amount;
        }

        container.appendChild(text);

        // Remove do DOM após a animação (1s)
        setTimeout(() => {
            text.remove();
        }, 1000);
    }

    // ===================================================================
    // FUNÇÃO: ATUALIZAR A LOJA (INTERFACE)
    // ===================================================================
    function updateShopUI() {
        // Segurança: Se não houver dados do jogador, não faz nada
        //if (!playerDataCache) return;
        console.log("Atualizando UI da Loja com dados do jogador:", playerDataCache);
        const p = playerDataCache;

        // 1. Atualiza o Saldo de Ouro no Topo
        let goldDisplay = document.getElementById('shop-gold-display');
        if (goldDisplay) goldDisplay.innerText = p.gold;
        
        // 2. Atualiza Quantidades do Inventário (O que você já tem)
        let elPotions = document.getElementById('shop-has-potions');
        let elPoints = document.getElementById('shop-has-points');
        
        if (elPotions) elPotions.innerText = p.potions;
        if (elPoints) elPoints.innerText = p.attribute_points;
        
        // 3. Tabela de Preços (Deve ser igual ao do PHP)
        const PRICES = {
            'potion': 50,
            'attribute_point': 100
        };

        // 4. Atualiza Estado dos Botões (Ativa/Desativa)
        const shopButtons = document.querySelectorAll('.btn-buy');
        
        shopButtons.forEach(btn => {
            let item = btn.dataset.item; // 'potion' ou 'attribute_point'
            const price = PRICES[item];
            
            if (price) {
                // Se o ouro atual é menor que o preço, desativa o botão
                if (p.gold < price) {
                    btn.disabled = true;
                    btn.innerText = "Sem Ouro";
                    btn.style.opacity = "0.5";
                    btn.style.cursor = "not-allowed";
                } else {
                    btn.disabled = false;
                    btn.innerText = "Comprar";
                    btn.style.opacity = "1";
                    btn.style.cursor = "pointer";
                }
            }
        });
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
        if (response.player) {
            playerDataCache = response.player;
        }
        // --- ABRIR LOJA (Vem do get_shop_data) ---
        if (response.view === 'open_shop') {
            // 1. Atualiza Cache
            playerDataCache = response.player;
            
            // 2. Atualiza HUD do Mapa (Para garantir que o ouro coletado no chão apareça)
            updateMapHud({ 
                gold: response.player.gold, 
                potions: response.player.potions, 
                hp: response.player.hp, 
                max_hp: response.player.base_stats.max_hp 
            });

            // 3. Preenche e Abre a Loja
            updateShopUI();
            document.getElementById('shop-modal').classList.remove('view-hidden');
            mapControls.style.pointerEvents = 'none';
        }

        // --- PÓS-COMPRA (Vem do buy_item) ---
        if (response.view === 'shop_update') {
            if (response.success) {
                // 1. Atualiza Cache Global (CRUCIAL: Agora temos menos ouro e mais itens)
                playerDataCache = response.player; 
                
                // 2. Atualiza a UI da Loja (Botões e Textos do Modal)
                updateShopUI(); 
                
                // 3. Atualiza o HUD do Mapa lá no fundo (Ouro, Poções, HP)
                // Isso resolve o seu problema de "reload nas poções e ouro"
                updateMapHud({ 
                    gold: response.player.gold, 
                    potions: response.player.potions, 
                    hp: response.player.hp, 
                    max_hp: response.player.base_stats.max_hp 
                });

                // 4. Feedback Visual
                const feedback = document.getElementById('shop-feedback');
                if (feedback) {
                    feedback.innerText = response.log;
                    feedback.style.color = '#2ecc71';
                    setTimeout(() => feedback.innerText = "", 2000);
                }
            } else {
                // Erro
                const feedback = document.getElementById('shop-feedback');
                if (feedback) {
                    feedback.innerText = response.error || "Erro.";
                    feedback.style.color = '#c0392b';
                }
            }
        }
        // --- RESPOSTA DE COMPRA ---
        if (response.view === 'shop_update') {
            
            if (response.success) {
                // 1. ATUALIZA O CACHE GLOBAL (Importantíssimo!)
                // Sem isso, o updateShopUI vai ler o ouro antigo
                playerDataCache = response.player; 
                
                // 2. REDESENHA O MODAL DA LOJA
                // Isso vai atualizar o texto do ouro e re-verificar os botões
                updateShopUI(); 
                
                // 3. Feedback Visual (Mensagem verde)
                const feedback = document.getElementById('shop-feedback');
                if (feedback) {
                    feedback.innerText = response.log;
                    feedback.style.color = '#2ecc71'; // Verde sucesso
                    setTimeout(() => { feedback.innerText = ""; }, 2000);
                }

                // 4. Atualiza o HUD do Mapa (para ficar sincronizado lá atrás)
                updateMapHud({ 
                    gold: response.player.gold, 
                    potions: response.player.potions, 
                    hp: response.player.hp, 
                    max_hp: response.player.base_stats.max_hp 
                });
            } else {
                // Se deu erro (ex: Ouro insuficiente no servidor)
                const feedback = document.getElementById('shop-feedback');
                if (feedback) {
                    feedback.innerText = response.error || "Erro na compra.";
                    feedback.style.color = '#c0392b'; // Vermelho erro
                }
            }
        }

        if (response.view === 'map') {
            stopBattleLoop();
            if (response.player && response.map_data) {
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
                }else if (['trap', 'treasure'].includes(response.event.type)) {
                    
                    // 1. Atualiza visualmente a célula (marca como completado)
                    const cell = document.getElementById(`cell-${response.player_pos.y}-${response.player_pos.x}`);
                    if (cell) {
                        cell.classList.remove('event', 'monster-event');
                        cell.classList.add('completed');
                    }

                    // 2. ATUALIZAÇÃO CRÍTICA: Se o evento alterou Ouro ou HP
                    // A resposta 'move' já traz 'hud_update', vamos usá-lo para atualizar a tela
                    if (response.hud_update) {
                        updateMapHud(response.hud_update);
                    }

                    // 3. Atualiza o cache global do jogador (se vier na resposta)
                    // Isso garante que se você abrir a loja ou personagem em seguida, o ouro esteja lá
                    if (response.player) {
                        playerDataCache = response.player;
                        // Atualiza a UI da loja em background para garantir sincronia
                        updateShopUI(); 
                    }

                    // 4. Libera movimento
                    mapControls.style.pointerEvents = 'auto';
                }// --- EVENTO DE LOJA ---
            else if (response.event.type === 'shop') {
                mapLog.innerText = "Você encontrou um mercador!";
                
                // Pequeno delay para ver a mensagem
                setTimeout(() => {
                    updateShopUI();
                    shopModal.classList.remove('view-hidden');
                }, 500);
            }
            } else {
                mapControls.style.pointerEvents = 'auto';
            }
            showView('map-view');

        } else if (response.view === 'battle') {
            mapControls.style.pointerEvents = 'auto';

            // Verifica se estamos ENTRANDO na batalha (vinda do mapa/menu)
            if (gameView.classList.contains('view-hidden')) {

                // 1. Mostra Loading
                loadingOverlay.classList.remove('view-hidden');

                // 2. Define URL do Fundo (Pode vir do PHP no futuro, por enquanto fixo)
                const bgUrl = 'assets/backgrounds/mountain_forest.png';

                // 3. Prepara as Promises (Herói + Monstro + Fundo)
                const p1 = setupSprites('player', response.battle_data.player_sprite_folder);
                const p2 = setupSprites('monster', response.battle_data.monster_sprite_folder);
                const p3 = preloadImage(bgUrl); // <--- NOVO

                currentHeroFolder = response.battle_data.player_sprite_folder;
                currentMonsterFolder = response.battle_data.monster_sprite_folder;

                lastPlayerHp = response.battle_data.player_hp;
                lastMonsterHp = response.battle_data.monster_hp;

                // 4. Espera TUDO carregar antes de mostrar a tela
                Promise.all([p1, p2, p3]).then(() => {

                    // Aplica o fundo agora que sabemos que está baixado
                    document.getElementById('battle-background').style.backgroundImage = `url('${bgUrl}')`;

                    updateBattleScreen(response.battle_data);
                    showView('game-view');

                    // Esconde Loading e Inicia Loop
                    loadingOverlay.classList.add('view-hidden');
                    if (!battleInterval) startBattleLoop();
                });

            } else {
                // Se já está na batalha (apenas atualizando HP/ATB), não faz reload
                updateBattleScreen(response.battle_data);
            }
        } else if (response.view === 'battle_over') {
            stopBattleLoop();
            if (response.battle_data) updateBattleScreen(response.battle_data);

            showView('game-view');
            menuArea.style.display = 'none';
            gameOverMessage.innerText = response.log;
            gameOverArea.style.display = 'block';

            if (response.hero_id === null) currentPlayerId = null;

        } else if (response.view === 'character_update') {
            // ATUALIZAÇÃO DO MODAL SEM RECARREGAR TUDO
            playerDataCache = response.player;
            updateCharacterSheet();
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
        if (av) { av.style.left = `${x * 40}px`; av.style.top = `${y * 40}px`; }
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

        if (playerContainer) { playerContainer.style.width = `${pSize}px`; playerContainer.style.height = `${pSize}px`; }
        if (monsterContainer) { monsterContainer.style.width = `${mSize}px`; monsterContainer.style.height = `${mSize}px`; }

        // Verifica Dano no PLAYER
        if (lastPlayerHp !== null && state.player_hp < lastPlayerHp) {
            const dmg = lastPlayerHp - state.player_hp;
            // O PHP não manda se foi critico no player, assumimos normal ou usamos logica
            showDamageText('player-sprite', dmg, 'normal');
        }
        // Verifica Cura no PLAYER
        else if (lastPlayerHp !== null && state.player_hp > lastPlayerHp) {
            const heal = state.player_hp - lastPlayerHp;
            showDamageText('player-sprite', heal, 'heal');
        }

        // Verifica Dano no MONSTRO
        if (lastMonsterHp !== null && state.monster_hp < lastMonsterHp) {
            const dmg = lastMonsterHp - state.monster_hp;

            // Detecta se foi crítico lendo o log (Solução simples sem mudar API)
            // Ou melhor: adicione 'is_crit' na resposta do PHP se quiser precisão
            const isCrit = state.log.includes("CRÍTICO");
            showDamageText('monster-sprite', dmg, isCrit ? 'crit' : 'normal');
        }

        // Atualiza o cache para o próximo tick
        lastPlayerHp = state.player_hp;
        lastMonsterHp = state.monster_hp;

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
            if (playerContainer) {
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
            if (monsterContainer) {
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
                if (['arrowup', 'w'].includes(key)) sendAction('move', { hero_id: currentPlayerId, direction: 'up' });
                if (['arrowdown', 's'].includes(key)) sendAction('move', { hero_id: currentPlayerId, direction: 'down' });
                if (['arrowleft', 'a'].includes(key)) sendAction('move', { hero_id: currentPlayerId, direction: 'left' });
                if (['arrowright', 'd'].includes(key)) sendAction('move', { hero_id: currentPlayerId, direction: 'right' });
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

    // --- FUNÇÃO DE ATUALIZAR MODAL ---
    function updateCharacterSheet() {
        if (!playerDataCache) {
            console.warn("Nenhum dado de jogador disponível para o modal de personagem.");
            return;
        }
        const p = playerDataCache; // Atalho

        // 1. Coluna Avatar
        document.getElementById('char-name').innerText = p.class_name;
        document.getElementById('char-class-name').innerText = "Herói"; // Poderia vir do DB
        document.getElementById('char-level').innerText = `Nível ${p.level}`;

        // XP Bar
        const xpPercent = p.exp_to_next_level > 0 ? (p.exp / p.exp_to_next_level) * 100 : 0;
        document.getElementById('char-xp-bar').style.width = `${xpPercent}%`;
        document.getElementById('char-xp-text').innerText = `${p.exp} / ${p.exp_to_next_level} XP`;

        // Pontos
        const points = p.attribute_points || 0;
        document.getElementById('char-points').innerText = points;

        // Sprite Preview
        if (currentHeroFolder) {
            document.getElementById('char-sprite-preview').style.backgroundImage = `url('${currentHeroFolder}idle.png')`;
        }

        // 2. Coluna Atributos Base
        // Nota: No DB 'speed' é usado para Agilidade, 'max_hp' base para Vitalidade
        document.getElementById('val-str').innerText = p.base_stats.strength;
        document.getElementById('val-agi').innerText = p.base_stats.agility;
        document.getElementById('val-luk').innerText = p.base_stats.luck;
        document.getElementById('val-vit').innerText = p.base_stats.max_hp; // Valor base bruto

        // Botões de Evolução (Só aparecem se tiver pontos)
        const btns = document.querySelectorAll('.btn-plus');
        btns.forEach(btn => {
            if (points > 0) btn.classList.add('visible');
            else btn.classList.remove('visible');
        });

        // 3. Coluna Status de Combate
        document.getElementById('total-atk').innerText = p.combat_stats.attack;
        document.getElementById('total-def').innerText = p.combat_stats.defense;
        document.getElementById('total-spd').innerText = p.combat_stats.speed || p.base_stats.speed; // Se não tiver speed calculado, usa base

        const crit = (p.combat_stats.crit_chance * 100).toFixed(1);
        document.getElementById('total-crit').innerText = `${crit}%`;

        const critMult = (p.combat_stats.crit_mult || 1.5) * 100;
        document.getElementById('total-crit-dmg').innerText = `${critMult}%`;

        document.getElementById('total-hp').innerText = p.base_stats.max_hp; // HP Max atual
    }

    // --- LISTENERS DO MODAL ---

    // Botão "Personagem" no Mapa
    const btnOpenChar = document.getElementById('btn-open-char');
    btnOpenChar.addEventListener('click', () => {
        updateCharacterSheet(); // Popula com dados atuais
        document.getElementById('character-modal').classList.remove('view-hidden');
    });

    // Botão "X" (Fechar)
    const btnCloseChar = document.getElementById('btn-close-char');
    btnCloseChar.addEventListener('click', () => {
        document.getElementById('character-modal').classList.add('view-hidden');
    });

    // Botões "+" (Distribuir Ponto)
    const plusButtons = document.querySelectorAll('.btn-plus');
    plusButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const attr = btn.dataset.attr; // strength, agility, luck, vitality
            // Envia requisição para a API
            sendAction('distribute_point', {
                hero_id: currentPlayerId,
                attribute: attr
            });
        });
    });

     btnCloseShop.addEventListener('click', () => {
        shopModal.classList.add('view-hidden');
        // Opcional: Mover o jogador "para trás" para não reativar a loja imediatamente?
        // Por enquanto, como eventos de mapa só disparam ao entrar na célula,
        // fechar o modal deixa você na célula da loja. Se mover e voltar, abre de novo.
        // Isso é o comportamento padrão de RPG.
        mapControls.style.pointerEvents = 'auto';
    });

    shopBuyButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const item = btn.dataset.item; // 'potion' ou 'attribute_point'
            sendAction('buy_item', { 
                hero_id: currentPlayerId, 
                item: item 
            });
            sendAction('get_shop_data', { hero_id: currentPlayerId });
        });
    });
});