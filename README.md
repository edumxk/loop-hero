# ⚔️ Loop Hero (Mini-RPG)

Este é um mini-RPG de batalha por turnos, inspirado em mecânicas de "loop", onde o herói enfrenta monstros para ganhar níveis e progredir. O jogo é construído com um backend PHP/SQLite e um frontend de navegador (HTML/CSS/JS).

## 🚀 Como Executar

Este projeto usa um backend PHP que necessita de um servidor e um driver de banco de dados específico.

### Requisitos

* **PHP** (v7.4 ou superior)
* **Driver PHP SQLite3**
* Um navegador web moderno

---

### 1. Instalação (Ambiente WSL / Ubuntu / Debian)

1.  **Clone este repositório** (ou baixe os arquivos).
2.  **Navegue até a pasta do projeto** pelo terminal.
3.  **Instale os módulos do PHP necessários:**
    ```bash
    # Atualiza a lista de pacotes
    sudo apt update
    
    # Instala o PHP-CLI (para o servidor) e o driver do SQLite
    sudo apt install php-cli php-sqlite3
    ```

### 2. Executando o Jogo

1.  **Inicie o servidor PHP embutido:**
    Na raiz do projeto (onde está o `index.html`), execute:
    ```bash
    php -S localhost:8000
    ```
2.  **Acesse no Navegador:**
    Abra seu navegador e vá para `http://localhost:8000`

### 3. Funcionamento

* **Banco de Dados:** O banco de dados (`game.db`) e a tabela (`heroes`) são criados **automaticamente** na primeira vez que você seleciona um herói, graças ao arquivo `/api/database.php`.
* **Persistência:** O progresso do seu herói (Nível, EXP, Atributos) é salvo no `game.db`.
* **Sessão:** O estado da batalha *atual* (HP do monstro, etc.) é guardado na `$_SESSION` do PHP e limpo ao final do combate.

---

## 🏛️ Estrutura do Projeto

/mini-rpg/
├── assets/
│   ├── heroes/
│   │   ├── human_knight.png
│   │   ├── dwarf_berserker.png
│   │   ├── (etc...)
│   └── (monstros...)
│
├── api/
│   ├── data/
│   │   ├── heroes.php      <-- ATUALIZADO
│   │   └── monsters.php    <-- ATUALIZADO
│   │
│   ├── logic/
│   │   ├── battle_logic.php
│   │   ├── monster_logic.php <-- NOVO
│   │   ├── player_logic.php  <-- NOVO
│   │
│   ├── database.php        <-- NOVO (Conexão DB)
│   ├── game.php            <-- ATUALIZADO (Controlador)
│   └── init_db.php         <-- NOVO (Cria o banco)
│
├── game.db                 <-- NOVO (O banco de dados)
│
├── index.html              <-- ATUALIZADO
├── style.css               <-- ATUALIZADO
└── script.js               <-- ATUALIZADO