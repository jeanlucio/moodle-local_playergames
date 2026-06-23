# Moodle Local PlayerGames

[![Moodle Plugin CI](https://github.com/jeanlucio/moodle-local_playergames/actions/workflows/ci.yml/badge.svg)](https://github.com/jeanlucio/moodle-local_playergames/actions/workflows/ci.yml)
![Moodle](https://img.shields.io/badge/Moodle-4.5%2B-orange?style=flat-square&logo=moodle&logoColor=white)
![License](https://img.shields.io/badge/License-GPLv3-blue?style=flat-square)
![Status](https://img.shields.io/badge/Status-In%20Development-yellow?style=flat-square)
[![PlayerGames Ecosystem](https://img.shields.io/badge/PlayerGames-Ecosystem-6f42c1?style=flat-square&logo=gamepad&logoColor=white)](https://moodle.org/plugins/browse.php?list=contributor&id=3970322)
![Core Component](https://img.shields.io/badge/Role-Central%20Hub-198754?style=flat-square)

> ⚠️ **This plugin is under active development.** It is not yet published on the Moodle Plugin Directory. Some features described below are planned and not yet implemented.

[English](#english) | [Português](#português)

---

## English

**PlayerGames** (`local_playergames`) is the central hub of the PlayerGames gamification ecosystem for Moodle. It serves four main purposes:

1. **Ecosystem Dashboard** — a visual overview of all Player plugins installed on the site, with status, dependencies, and quick-access links.
2. **Shared AI Key Hub** — a single place where administrators and teachers configure API keys (Gemini, Groq, OpenAI-compatible) shared across all Player plugins.
3. **Player Hub** — a site-wide gamification platform for students and/or other Moodle users (teachers, managers, administrators), with XP, levels, seasons, missions, achievements, and daily streak. The administrator configures which groups participate.
4. **Daily Mini-games** — four concept-reinforcement mini-games and a daily check-in powered by content cartridges (JSON), working as a Duolingo-style learning loop.

---

### ✨ Features

#### ✅ Implemented

* 🗺️ **Ecosystem Dashboard:** SVG overview of all Player plugins — installed, missing, dependencies, status, and quick-action links for admins.
* 🔑 **Shared AI Key Hub:** Configure Gemini, Groq, and OpenAI-compatible API keys once — all Player plugins consume them automatically via a 4-level priority chain.
* 📊 **Engagement Meter:** Compare engagement metrics (events per student, completion rate, average grade) between courses that use Player plugins and those that don't — available to admins (all courses) and teachers (their own courses).
* 🎮 **Player Hub — XP & Levels:** Site-wide XP across configurable seasons. Each mini-game awards a fixed amount of XP, and admins set how many scoring plays per day each game allows — XP is earned exclusively through gameplay, so teachers with different course loads and students enrolled in different numbers of courses all compete on equal footing.
* 🪜 **Player Hub — Configurable Level Ladder:** Admins edit the per-level XP thresholds and titles from the Level Ladder page — restore the default 5-tier ladder or generate a longer linear progression with one click.
* 📅 **Player Hub — Daily Check-in:** Earn XP just for visiting the hub once a day, capped per season. Optionally counts toward the daily streak.
* 🏆 **Player Hub — Ranking:** Season ranking with privacy controls (opt-in), separated by participant group (students vs. non-students — teachers, managers, admins). Admins and managers see both groups via tabs.
* 🔥 **Player Hub — Streak & Freeze:** Daily streak tracking. Freeze consumables prevent streak loss and are earned via missions; the daily check-in can keep the streak alive when configured.
* 🎯 **Player Hub — Missions:** Daily, streak-based, cumulative XP, and victory-based missions with configurable XP rewards.
* 🏅 **Player Hub — Achievements:** Permanent achievements that persist across seasons.
* 🏷️ **Player Hub — Titles:** Level-based titles visible in Moodle profiles, forums, and courses.
* 📦 **Cartridge System:** Content source for the mini-games. Supports multiple active cartridges simultaneously.
  * **All games:** manual creation (inline editor), JSON upload, or AI generation (Gemini/Groq/OpenAI).
  * **PlayerQuiz and PlayerBattle:** also accept the **Moodle Question Bank** (multiple-choice questions only).
  * **PlayerGuess and PlayerFill:** also accept the **Moodle Glossary** (terms and definitions reused as-is).
* 🧠 **PlayerQuiz:** Daily multiple-choice mini-game using concepts from the active cartridge. Wrong answer → new concept; correct answer → XP. Replaying within the same day serves fresh questions instead of repeating ones already seen.
* 📅 **Season Management:** Create, close, and auto-renew seasons with configuration snapshots. Historical data is preserved when a season closes.
* 🔐 **Privacy (GDPR):** Complete Privacy Provider — metadata declaration, export and deletion of all stored personal data; shared cartridges are preserved with the uploader anonymised.
* 🧪 **Automated Tests:** 142-case PHPUnit suite, green across the full CI matrix (see the Automated Tests section).

#### ⏳ In Development / Planned

* 🔡 **PlayerGuess:** Wordle-style mini-game — guess the term letter by letter (5–8 letters, configurable). Six attempts before the answer is revealed.
* 📝 **PlayerFill:** Crossword-style mini-game — numbered positions; the same number shares the same letter across multiple words; solving one word reveals letters in others (cascade effect). Grid generated in PHP without external libraries.
* ⚔️ **PlayerBattle:** Match-3 RPG mini-game (8×8 grid) with turn-based combat against a boss powered by Phaser 3. Combining mana pieces charges a question; correct answer → triple damage; wrong answer → player takes damage.
* 📦 **Phaser Centralized:** `local_playergames` will serve `phaser.min.js` to all Player plugins via `local_playergames_get_phaser_url()`, removing duplicated copies from each plugin.
* 🧩 **block_playergames:** Companion sidebar block showing the user's current XP, level, streak, and daily game status on any Moodle page, linking to the full Player Hub.
* 🛡️ **Publication Polish:** Full accessibility audit and Behat acceptance tests (PHPUnit suite and PHPCS compliance are already in place).

---

### 🤖 AI Provider Chain

PlayerGames resolves an AI provider **level-first**: an explicitly configured key always wins over the institutional default. The first tier with a usable key is used, and a failing call (network error, timeout, HTTP error) automatically falls through to the next available option.

**Resolution order:**

| Priority | Source | Notes |
|----------|--------|-------|
| 1 | **Personal keys** (*My AI Keys* page) | The teacher's own Gemini / Groq / OpenAI-compatible keys, including a personal OpenAI-compatible base URL and model. Tried first, so a personal key always wins. |
| 2 | **Site-wide keys** (PlayerGames settings) | Keys the admin configured for the whole site. |
| 3 | **Moodle `core_ai`** | Institutional default, tried **last**. Uses whichever AI providers the admin configured in *Site administration → AI → AI providers*; on Moodle 5.2+, `core_ai` has its own internal fallback between providers. No API key needed in PlayerGames. |

Within tiers 1 and 2, the direct providers are tried in a fixed order: **Gemini → Groq → OpenAI-compatible**. Each direct call enforces JSON output mode where supported.

> **Origin beats provider.** If a personal Groq key exists (tier 1) and the admin also configured `core_ai` (tier 3), the personal Groq key is used because tier 1 is tried first — the explicit key always wins over the institutional default.

**Integration API for other plugins:**

Other plugins in the Player ecosystem can delegate AI calls to PlayerGames without managing keys themselves:

```php
use local_playergames\cartridge\ai_generator;
use local_playergames\api_key_helper;

// Check availability before showing any AI UI
if (class_exists(ai_generator::class) && api_key_helper::has_any_key()) {
    $gen = new ai_generator();
    $result = $gen->send('Your custom prompt here');
    // $result['success'] (bool), $result['data'] (string), $result['provider'] (string)
}
```

- `api_key_helper::has_any_key()` — returns `true` if at least one provider is configured.
- `ai_generator::send(string $prompt): array` — sends a raw prompt through the full provider chain and returns the raw text response. Use this when the calling plugin has its own prompt format and response parser.
- `ai_generator::generate(...)` — use only when you need concept arrays in `{term, definition, category, difficulty}` format.

---

### 📦 Cartridge Format

Content cartridges are JSON files that power all mini-games. The same pack feeds PlayerQuiz, PlayerGuess, PlayerFill, and PlayerBattle:

```json
{
  "name": "Gamification Fundamentals",
  "version": "1.0",
  "language": "en",
  "concepts": [
    {
      "term": "gamification",
      "definition": "Use of game elements in non-game contexts",
      "category": "fundamentals",
      "difficulty": 2
    }
  ]
}
```

- Validated by the importer before saving
- Terms for wordle-style games: filtered by configurable length (default 4–8 letters), alphabetic characters only
- Admin can have multiple active cartridges simultaneously
- Multi-language: each cartridge declares its `language`

---

### 🧠 How PlayerQuiz Works

PlayerQuiz is the first daily mini-game. It draws multiple-choice questions from the active cartridges (and, optionally, the Moodle Question Bank) and shows them **one at a time**.

**The play loop**

- One question is shown at a time, with a per-question countdown.
- **Wrong answer** (or the timer running out) → the correct option is revealed, then the next question loads.
- **Correct answer** → the play ends and XP is awarded.
- With an attempt limit set, running out of attempts ends the play in **failure** (no XP) and may start a cooldown.

Because a play ends on the **first correct answer**, a player rarely answers the whole pool — most questions in a session are just a buffer that provides variety and room to be wrong before repeating.

**Two independent limits (this trips people up)**

| Setting | Axis | Meaning |
|---|---|---|
| **Games per day** (`xp_games_quiz`) | the *day* | How many **plays** earn XP per day. Also sets the daily XP cap: `XP per game × games per day`. |
| **Questions per session** (`quiz_session_size`) | inside *one play* | How many questions are drawn into a **single play** (the "deck" of that round). |

*Example* — with *XP per game = 25*, *games per day = 3*, *questions per session = 20*: a player can complete **3 plays** today (cap 75 XP), and **each** play is built from a fresh draw of **20** questions. The two numbers answer different questions: *how many rounds today* vs *how big each round's deck is*.

**Fresh questions and repeats**

- Each page load builds a **brand-new shuffled session** — there is no saved "session of the day".
- Questions are remembered as "seen today" **only when a play is completed with a correct answer**. Those are then excluded from the **other plays of the same day**, so completed plays never repeat questions.
- **Leaving mid-play or failing records nothing** — reopening the page gives a fresh random draw that may include the same questions.
- The "seen today" memory is **per day**: the next day starts clean and may re-draw questions from previous days. There is no cross-day exclusion.
- Reloading never grants extra XP — it only changes *which* questions appear; XP is still capped by *games per day* and only counts on a correct answer.

**Admin settings** (Site administration → Plugins → Local plugins → PlayerGames → Game rules)

| Setting | Default | What it does |
|---|---|---|
| **Time per question (seconds)** | 120 (2 min) | Countdown per question. Reaching zero counts as a wrong answer and advances. `0` disables the timer. |
| **Maximum attempts** | 0 (unlimited) | Wrong answers (including timeouts) that end a play without XP. `0` keeps the original behaviour — the game continues until the player answers correctly. |
| **Cooldown after failing (minutes)** | 0 | When a play ends in failure, the quiz is locked for this many minutes. Persisted server-side (a page reload does **not** bypass it). Only meaningful together with an attempt limit. |
| **Questions per session** | 20 | Size of each play's draw, chosen from a capped list (5–50). |

**How the timer, attempts and cooldown interlock**

- A timer expiry is treated exactly like a wrong answer.
- A play can only **fail** when a maximum-attempts limit is set; with unlimited attempts (`0`) the game loops until a correct answer, so the cooldown never triggers.
- A failed play awards **no XP and does not consume a daily play** — the cooldown is the only throttle, so with cooldown `0` the player may retry immediately.

**Why the session size is capped**

Every question of a session is rendered into the page HTML at once (the browser just reveals them one by one). A very large session would format and ship hundreds of questions the player will never see — wasted server work and a heavy page. The capped list (max 50) keeps plenty of variety without that cost. Truly large sessions would require a different architecture (loading questions on demand), which is not how the game works today.

---

### 🎓 Educational Purpose

PlayerGames is designed to:

* Reinforce daily learning through spaced repetition (Duolingo-like loop)
* Motivate students to access the platform more frequently, building familiarity with the learning environment in a natural and engaging way
* Foster healthy competition between students and teachers across different courses, regardless of how many courses each participant is enrolled in or teaches
* Integrate students and teachers from different courses around a common gamified experience
* Promote active learning habits through daily challenges and streak mechanics
* Explore diverse knowledge areas through concept cartridges — from discipline-specific content to cross-cutting topics like foreign language learning
* Give institutional visibility into the impact of gamification (Engagement Meter)

Suitable for:

* Institutions with multiple gamified courses using block_playerhud
* Administrators and managers looking to measure gamification impact
* Any Moodle user group (students, teachers, managers, admins) that can be configured to participate in the Hub

---

### 🕹️ PlayerGames Ecosystem

PlayerGames is the hub of a broader gamification ecosystem. Together, these plugins transform Moodle into an immersive experience:

* **PlayerHUD Block:** XP, levels, inventory, drops, quests, RPG classes, story, karma, and ranking inside each course.
  👉 https://github.com/jeanlucio/moodle-block_playerhud

* **PlayerHUD Filter:** Enables item drops via shortcodes inside course content.
  👉 https://github.com/jeanlucio/moodle-filter_playerhud

* **PlayerHUD Availability Restriction:** Restricts access to course activities based on the student's current level or collected items.
  👉 https://github.com/jeanlucio/moodle-availability_playerhud

* **PlayerGroup:** Lets students autonomously form their own groups directly from the activity page.
  👉 https://github.com/jeanlucio/moodle-mod_playergroup

---

### 📦 Requirements

| Component | Version |
|-----------|---------|
| Moodle    | 4.5+    |
| PHP       | 8.1+    |

---

### 🛠️ Installation

> ⚠️ This plugin is not yet published on the Moodle Plugin Directory. Install manually from this repository.

1. Download the `.zip` file or clone this repository.
2. Extract the folder into your Moodle `local/` directory.
3. Rename the folder to `playergames` (if necessary).
   Final path: `your-moodle/local/playergames/`
4. Visit **Site administration → Notifications** to complete installation.
5. Go to **Site administration → Plugins → Local plugins → PlayerGames** to configure API keys and season settings.
6. Create the first season via the **Season Management** page.

---

### 📖 Usage

#### For Administrators

1. Install the plugin and complete the Moodle upgrade step.
2. Configure global AI API keys in *Site administration → Plugins → Local plugins → PlayerGames → API Keys*.
3. Create the first season in the **Season Management** page, setting name, start/end dates, and the per-game XP and plays-per-day rewards.
4. Optionally tune the **Level Ladder** page — adjust XP thresholds and titles, restore the default ladder, or generate a longer linear one.
5. Upload or generate a content cartridge in the **Cartridge** page.
6. Monitor gamification impact in the **Engagement Meter** page.

#### For Teachers

1. Access **My AI Keys** to configure personal API keys (optional — site keys work if configured).
2. Use the PlayerGames hub or any Player plugin — AI features will use the configured key chain automatically.

#### For Students

1. Visit the **Player Hub** to see your XP, level, ranking position, streak, and daily missions.
2. Play the daily mini-games to earn XP.
3. Toggle "Show in ranking" in your profile to control ranking visibility.

---

### 🧪 Automated Tests

PlayerGames ships with a PHPUnit suite covering the gamification engine, the cartridge pipeline, scheduled tasks, privacy and events. Every CI push runs the full matrix (Moodle 4.5 → 5.2, PostgreSQL & MariaDB).

#### PHPUnit — Unit & Integration Tests

| Test file | Cases | What is covered |
|-----------|------:|-----------------|
| `cartridge/importer_test.php` | 9 | Concept and quiz import; type inference; difficulty clamping; category dedup; schema-error paths |
| `cartridge/exporter_test.php` | 4 | Concept/quiz export structure and full import→export round-trip including root metadata |
| `cartridge/category_manager_test.php` | 7 | Category CRUD, incrementing sortorder, idempotent `ensure`, ownership guard, concept null-on-delete |
| `cartridge/quiz_generator_test.php` | 7 | Quiz response parsing, category/difficulty defaults, `save_standalone` persistence |
| `cartridge/ai_generator_test.php` | 5 | Concept response parser: wrapped/bare/fenced JSON, invalid and missing-concepts errors |
| `hub/xp_manager_test.php` | 7 | Level thresholds, per-game cap enforcement, uncapped mission award, level-up event |
| `hub/daily_play_manager_test.php` | 3 | Multiple plays split XP evenly; play beyond the daily quota rejected; last play trimmed to the exact cap |
| `hub/level_manager_test.php` | 8 | Ladder seeding, XP/title lookups, save renumber + zero floor, restore defaults, linear generation and bounds |
| `hub/checkin_manager_test.php` | 9 | Check-in insert + XP award, idempotency, season cap, optional streak advance, participant eligibility |
| `hub/served_questions_test.php` | 3 | Per-day "already shown" set grouped by source, idempotent scoping, invalid items ignored |
| `hub/streak_manager_test.php` | 9 | Streak start/continue/reset, freeze consumption, overnight break processing |
| `hub/season_manager_test.php` | 8 | Season lifecycle, active/upcoming resolution, exclusive activation, snapshot, `create_next` |
| `hub/mission_manager_test.php` | 7 | Mission sync, progress/completion with XP reward, daily and missed check-in resets |
| `hub/achievement_manager_test.php` | 6 | Achievement sync, granting (first game/level/all-games-day), idempotency |
| `hub/title_manager_test.php` | 2 | Level→title key clamping and translation |
| `observer_test.php` | 2 | `game_completed` drives streak/mission/achievement; records streak even without a season |
| `games/quiz_loader_test.php` | 10 | Cartridge source: completeness filter, session size, active-only, id filter, metadata, random draw, fresh-question exclusion and pool reuse |
| `games/season_game_config_test.php` | 7 | Source helpers; enabled-record lookup; per-season listing; default seeding and preservation |
| `task/assign_daily_games_test.php` | 3 | Per-game concept assignment, idempotency, no-cartridge case |
| `task/reset_daily_missions_test.php` | 1 | Daily mission reset + streak break orchestration |
| `task/close_expired_seasons_test.php` | 2 | Closes expired season; auto-renew creates and activates next |
| `task/purge_old_scores_test.php` | 2 | Retention-window purge; keep-within-window no-op |
| `privacy/provider_test.php` | 8 | Metadata, contexts, userlist, export, and the three deletion paths |
| `api_key_helper_test.php` | 5 | Personal/site key resolution, OpenAI defaults, `has_any_key` |
| `local/engagement_report_test.php` | 4 | Empty metrics, course counting, player-course detection, scope split |
| `ecosystem/plugin_registry_test.php` | 2 | Catalog structure and unique components |
| `ecosystem/plugin_status_test.php` | 1 | Installed status keyed by component; hub reported installed |
| `event/events_test.php` | 1 | All nine events trigger, are captured and render a description |
| **Total** | **142** | |

```bash
vendor/bin/phpunit --testsuite local_playergames
```

---

### 🔎 Third-party Service Disclosure

PlayerGames includes optional AI-powered features for content generation (cartridges) and cartridge AI generation. These are entirely optional — all content can be created manually.

#### Supported Providers

- **Google Gemini** — https://ai.google.dev/
- **Groq** — https://console.groq.com/
- **OpenAI-compatible APIs** — Any provider following the OpenAI API format (e.g. OpenRouter, self-hosted models via LM Studio, Ollama proxy, etc.)

These services operate under their own terms of service and privacy policies.

#### Data Transmission

When the AI feature is used, user-entered prompts are transmitted to the selected provider for processing. The plugin:
- Does not store prompts or AI responses
- Only stores the cartridge concepts created inside Moodle

No external communication occurs unless an AI feature is explicitly used.

---

## 📄 License / Licença

This project is licensed under the **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

---

## Português

> ⚠️ **Este plugin está em desenvolvimento ativo.** Ainda não foi publicado no Diretório de Plugins do Moodle. Algumas funcionalidades descritas abaixo são planejadas e ainda não estão implementadas.

O **PlayerGames** (`local_playergames`) é o hub central do ecossistema de gamificação PlayerGames para o Moodle. Serve a quatro propósitos principais:

1. **Dashboard do Ecossistema** — visão geral visual de todos os plugins Player instalados no site, com status, dependências e links de acesso rápido.
2. **Hub Central de Chaves de IA** — um único lugar onde administradores e professores configuram chaves de API (Gemini, Groq, compatível com OpenAI) compartilhadas entre todos os plugins Player.
3. **Player Hub** — plataforma de gamificação site-wide para estudantes e/ou outros usuários do Moodle (professores, managers, administradores), com XP, níveis, temporadas, missões, conquistas e streak diário. O administrador configura quais grupos participam.
4. **Minijogos Diários** — quatro minijogos de reforço de conceitos e um check-in diário, alimentados por cartuchos de conteúdo (JSON) — funcionando como um loop de aprendizado estilo Duolingo.

---

### ✨ Funcionalidades

#### ✅ Implementado

* 🗺️ **Dashboard do Ecossistema:** Visão SVG de todos os plugins Player — instalados, ausentes, dependências, status e links de ação rápida para admins.
* 🔑 **Hub Central de Chaves de IA:** Configure chaves Gemini, Groq e compatíveis com OpenAI uma única vez — todos os plugins Player as consomem automaticamente via cadeia de prioridade com 4 níveis.
* 📊 **Engajômetro:** Compare métricas de engajamento (eventos por aluno, taxa de conclusão, nota média) entre cursos com e sem plugins Player — disponível para admins (todos os cursos) e professores (apenas os seus).
* 🎮 **Player Hub — XP e Níveis:** XP site-wide ao longo de temporadas configuráveis. Cada minijogo concede uma quantidade fixa de XP, e o admin define quantas jogadas pontuáveis por dia cada jogo permite — o XP é ganho exclusivamente jogando, então professores com diferentes cargas de turmas e estudantes matriculados em quantidades diferentes de cursos competem em igualdade de condições.
* 🪜 **Player Hub — Escada de Níveis Configurável:** O admin edita os limiares de XP e os títulos de cada nível na página Escada de Níveis — restaura a escada padrão de 5 faixas ou gera uma progressão linear mais longa com um clique.
* 📅 **Player Hub — Check-in Diário:** Ganhe XP apenas por acessar o hub uma vez ao dia, com limite por temporada. Opcionalmente conta para o streak diário.
* 🏆 **Player Hub — Ranking:** Ranking por temporada com controle de privacidade (opt-in), separado por grupo de participantes (estudantes vs. não-estudantes — professores, managers, admins). Admins e managers veem ambos os grupos em abas.
* 🔥 **Player Hub — Streak e Freeze:** Acompanhamento de streak diário. Consumíveis de freeze evitam a perda de streak e são ganhos via missões; o check-in diário pode manter o streak vivo quando configurado.
* 🎯 **Player Hub — Missões:** Missões diárias, por streak, cumulativas de XP e por vitória, com recompensas de XP configuráveis.
* 🏅 **Player Hub — Conquistas:** Conquistas permanentes que persistem entre temporadas.
* 🏷️ **Player Hub — Títulos:** Títulos baseados em nível visíveis no perfil Moodle, fóruns e cursos.
* 📦 **Sistema de Cartuchos:** Fonte de conteúdo para os minijogos. Múltiplos cartuchos ativos simultaneamente.
  * **Todos os jogos:** criação manual (editor inline), upload de JSON ou geração com IA (Gemini/Groq/OpenAI).
  * **PlayerQuiz e PlayerBattle:** aceitam também o **Banco de Questões do Moodle** (apenas questões de múltipla escolha).
  * **PlayerGuess e PlayerFill:** aceitam também o **Glossário do Moodle** (termos e definições reaproveitados sem configuração adicional).
* 🧠 **PlayerQuiz:** Minijogo diário de múltipla escolha usando conceitos do cartucho ativo. Errou → novo conceito; acertou → XP. Rejogar no mesmo dia traz questões novas em vez de repetir as já vistas.
* 📅 **Gerenciamento de Temporadas:** Criar, fechar e renovar automaticamente temporadas com snapshots de configuração. O histórico é preservado ao fechar uma temporada.
* 🔐 **Privacidade (LGPD/GDPR):** Privacy Provider completo — declaração de metadados, export e deleção de todos os dados pessoais armazenados; cartuchos compartilhados são preservados com o autor anonimizado.
* 🧪 **Testes Automatizados:** Suíte PHPUnit com 142 casos, verde na matriz completa do CI (ver a seção Testes Automatizados).

#### ⏳ Em Desenvolvimento / Planejado

* 🔡 **PlayerGuess:** Minijogo estilo Wordle — adivinhe o termo letra a letra (5 a 8 letras, configurável). Seis tentativas antes de revelar a resposta.
* 📝 **PlayerFill:** Minijogo de cruzadinha — posições numeradas; o mesmo número compartilha a mesma letra entre múltiplas palavras; resolver uma palavra revela letras nas demais (efeito cascata). Grid gerado em PHP sem bibliotecas externas.
* ⚔️ **PlayerBattle:** Minijogo match-3 RPG (grid 8×8) com combate por turnos contra um boss, movido pelo Phaser 3. Combinar peças de mana carrega uma pergunta do cartucho; acertar → dano triplo no boss; errar → o jogador leva dano.
* 📦 **Phaser Centralizado:** O `local_playergames` passará a servir o `phaser.min.js` para todos os plugins Player via `local_playergames_get_phaser_url()`, eliminando cópias duplicadas em cada plugin.
* 🧩 **block_playergames:** Bloco sidebar companheiro exibindo XP, nível, streak e status dos jogos diários do usuário em qualquer página do Moodle, com link para o Player Hub completo.
* 🛡️ **Polimento para Publicação:** Auditoria completa de acessibilidade e testes de aceitação Behat (a suíte PHPUnit e a conformidade PHPCS já estão prontas).

---

### 🤖 Cadeia de Provedores de IA

O PlayerGames resolve o provedor de IA por **nível primeiro**: uma chave configurada explicitamente sempre vence o padrão institucional. O primeiro nível com uma chave utilizável é usado; se uma chamada falhar (erro de rede, timeout, erro HTTP), a próxima opção disponível é tentada automaticamente.

**Ordem de resolução:**

| Prioridade | Origem | Observações |
|------------|--------|-------------|
| 1 | **Chaves pessoais** (página *Minhas Chaves de IA*) | As chaves Gemini / Groq / compatível-com-OpenAI do próprio professor, incluindo base URL e modelo OpenAI pessoais. Tentadas primeiro, então uma chave pessoal sempre vence. |
| 2 | **Chaves globais** (configurações do PlayerGames) | Chaves que o admin cadastrou para o site inteiro. |
| 3 | **Moodle `core_ai`** | Padrão institucional, tentado **por último**. Usa os provedores configurados pelo admin em *Administração do site → IA → Provedores de IA*; no Moodle 5.2+, o `core_ai` tem fallback interno entre provedores. Nenhuma chave precisa ser cadastrada no PlayerGames. |

Dentro dos níveis 1 e 2, os provedores diretos são tentados numa ordem fixa: **Gemini → Groq → compatível-com-OpenAI**. Cada chamada direta força o modo de saída JSON quando suportado.

> **A origem prevalece sobre o provedor.** Se existe uma chave Groq pessoal (nível 1) e o admin também configurou o `core_ai` (nível 3), a chave Groq pessoal é usada porque o nível 1 é tentado primeiro — a chave explícita sempre vence o padrão institucional.

**API de integração para outros plugins:**

Outros plugins do ecossistema Player podem delegar chamadas de IA ao PlayerGames sem gerenciar chaves diretamente:

```php
use local_playergames\cartridge\ai_generator;
use local_playergames\api_key_helper;

// Verificar disponibilidade antes de exibir qualquer interface de IA
if (class_exists(ai_generator::class) && api_key_helper::has_any_key()) {
    $gen = new ai_generator();
    $result = $gen->send('Seu prompt personalizado aqui');
    // $result['success'] (bool), $result['data'] (string), $result['provider'] (string)
}
```

- `api_key_helper::has_any_key()` — retorna `true` se ao menos um provedor estiver configurado.
- `ai_generator::send(string $prompt): array` — envia um prompt livre pela cadeia completa de provedores e retorna o texto bruto. Use quando o plugin chamador tem seu próprio formato de prompt e parser de resposta.
- `ai_generator::generate(...)` — use apenas quando precisar de arrays de conceitos no formato `{term, definition, category, difficulty}`.

---

### 📦 Formato do Cartucho

Os cartuchos de conteúdo são arquivos JSON que alimentam todos os minijogos. O mesmo pacote serve ao PlayerQuiz, PlayerGuess, PlayerFill e PlayerBattle:

```json
{
  "name": "Fundamentos de Gamificação",
  "version": "1.0",
  "language": "pt_BR",
  "concepts": [
    {
      "term": "gamificação",
      "definition": "Uso de elementos de jogos em contextos não-lúdicos",
      "category": "fundamentos",
      "difficulty": 2
    }
  ]
}
```

- Validado pelo importador antes de salvar
- Termos para jogos estilo Wordle: filtrados por comprimento configurável (padrão 4–8 letras), apenas caracteres alfabéticos
- Admin pode ter múltiplos cartuchos ativos simultaneamente
- Multi-idioma: cada cartucho declara seu `language`

---

### 🧠 Como o PlayerQuiz Funciona

O PlayerQuiz é o primeiro mini-jogo diário. Ele sorteia questões de múltipla escolha dos cartuchos ativos (e, opcionalmente, do Banco de Questões do Moodle) e as exibe **uma de cada vez**.

**O fluxo da jogada**

- Uma questão é exibida por vez, com uma contagem regressiva por pergunta.
- **Erro** (ou o tempo esgotar) → a alternativa correta é revelada e a próxima questão carrega.
- **Acerto** → a jogada termina e o XP é concedido.
- Com um limite de tentativas definido, esgotar as tentativas encerra a jogada em **falha** (sem XP) e pode iniciar um cooldown.

Como a jogada termina no **primeiro acerto**, o jogador raramente responde o pool inteiro — a maioria das questões da sessão é só um colchão que dá variedade e margem para errar antes de repetir.

**Dois limites independentes (é aqui que muita gente se confunde)**

| Configuração | Eixo | Significado |
|---|---|---|
| **Jogos por dia** (`xp_games_quiz`) | o *dia* | Quantas **jogadas** valem XP por dia. Também define o teto diário de XP: `XP por jogo × jogos por dia`. |
| **Questões por sessão** (`quiz_session_size`) | dentro de *uma jogada* | Quantas questões são sorteadas para uma **única jogada** (o "baralho" daquela partida). |

*Exemplo* — com *XP por jogo = 25*, *jogos por dia = 3*, *questões por sessão = 20*: o jogador pode concluir **3 jogadas** hoje (teto de 75 XP), e **cada** jogada é montada a partir de um sorteio novo de **20** questões. Os dois números respondem perguntas diferentes: *quantas partidas hoje* vs *qual o tamanho do baralho de cada partida*.

**Questões novas e repetições**

- Cada carregamento da página monta uma **sessão nova e embaralhada** — não existe uma "sessão do dia" guardada.
- As questões só são lembradas como "vistas hoje" **quando uma jogada é concluída com acerto**. Elas então são excluídas das **outras jogadas do mesmo dia**, então jogadas concluídas nunca repetem questões.
- **Sair no meio ou falhar não grava nada** — reabrir a página gera um sorteio novo que pode incluir as mesmas questões.
- A memória de "vistas hoje" é **por dia**: o dia seguinte começa limpo e pode re-sortear questões de dias anteriores. Não há exclusão entre dias.
- Recarregar nunca dá XP extra — só muda *quais* questões aparecem; o XP continua limitado por *jogos por dia* e só conta no acerto.

**Configurações do administrador** (Administração do site → Plugins → Plugins locais → PlayerGames → Regras dos jogos)

| Configuração | Padrão | O que faz |
|---|---|---|
| **Tempo por questão (segundos)** | 120 (2 min) | Contagem regressiva por questão. Chegar a zero conta como erro e avança. `0` desativa o cronômetro. |
| **Máximo de tentativas** | 0 (ilimitado) | Erros (incluindo tempo esgotado) que encerram a jogada sem XP. `0` mantém o comportamento original — o jogo continua até o jogador acertar. |
| **Cooldown ao falhar (minutos)** | 0 | Quando uma jogada termina em falha, o quiz fica bloqueado por estes minutos. Persistido no servidor (recarregar a página **não** contorna). Só faz sentido junto com um limite de tentativas. |
| **Questões por sessão** | 20 | Tamanho do sorteio de cada jogada, escolhido em uma lista com teto (5–50). |

**Como cronômetro, tentativas e cooldown se encaixam**

- O tempo esgotar é tratado exatamente como um erro.
- Uma jogada só pode **falhar** quando há um limite de tentativas; com tentativas ilimitadas (`0`) o jogo entra em loop até o acerto, então o cooldown nunca dispara.
- Uma jogada falha **não dá XP e não consome jogada do dia** — o cooldown é o único freio, então com cooldown `0` o jogador pode tentar de novo na hora.

**Por que o tamanho da sessão tem teto**

Toda questão da sessão é renderizada no HTML da página de uma vez (o navegador apenas vai revelando uma a uma). Uma sessão muito grande formataria e enviaria centenas de questões que o jogador nunca verá — trabalho de servidor desperdiçado e página pesada. A lista com teto (máx. 50) mantém variedade de sobra sem esse custo. Sessões realmente grandes exigiriam outra arquitetura (carregar questões sob demanda), o que não é como o jogo funciona hoje.

---

### 🎓 Finalidade Educacional

O PlayerGames foi projetado para:

* Reforçar aprendizado diário por repetição espaçada (loop estilo Duolingo)
* Motivar os estudantes a acessar a plataforma com mais frequência, desenvolvendo familiaridade com o ambiente educacional de forma natural e engajante
* Fomentar uma competição saudável entre estudantes e professores de diferentes cursos, independentemente de quantos cursos cada participante frequenta ou leciona
* Integrar estudantes e professores de diferentes cursos em torno de uma experiência gamificada comum
* Promover um processo de aprendizado ativo por meio de desafios diários e mecânicas de streak
* Explorar áreas de conhecimento diversas por meio de cartuchos de conceitos — de conteúdo disciplinar específico a temas transversais como o aprendizado de um novo idioma
* Dar visibilidade institucional ao impacto da gamificação (Engajômetro)

Indicado para:

* Instituições com múltiplos cursos gamificados usando o `block_playerhud`
* Administradores e gestores que queiram medir o impacto da gamificação
* Qualquer grupo de usuários do Moodle (estudantes, professores, managers, admins) que possa ser configurado para participar do Hub

---

### 🕹️ Ecossistema PlayerGames

O PlayerGames é o hub de um ecossistema mais amplo de gamificação. Juntos, esses plugins transformam o Moodle em uma experiência imersiva:

* **Bloco PlayerHUD:** XP, níveis, inventário, drops, missões, classes RPG, história, karma e ranking dentro de cada curso.
  👉 https://github.com/jeanlucio/moodle-block_playerhud

* **Filtro PlayerHUD:** Permite inserir drops de itens por meio de shortcodes no conteúdo do curso.
  👉 https://github.com/jeanlucio/moodle-filter_playerhud

* **Restrição de Acesso PlayerHUD:** Restringe o acesso a atividades com base no nível atual do aluno ou nos itens coletados.
  👉 https://github.com/jeanlucio/moodle-availability_playerhud

* **PlayerGroup:** Permite que os alunos formem seus próprios grupos de forma autônoma diretamente na página da atividade.
  👉 https://github.com/jeanlucio/moodle-mod_playergroup

---

### 📦 Requisitos

| Componente | Versão |
|------------|--------|
| Moodle     | 4.5+   |
| PHP        | 8.1+   |

---

### 🛠️ Instalação

> ⚠️ Este plugin ainda não está publicado no Diretório de Plugins do Moodle. Instale manualmente a partir deste repositório.

1. Baixe o arquivo `.zip` ou clone este repositório.
2. Extraia na pasta `local/` do seu Moodle.
3. Renomeie para `playergames` (se necessário).
   Caminho final: `seu-moodle/local/playergames/`
4. Acesse **Administração do site → Notificações** para concluir a instalação.
5. Vá em **Administração do site → Plugins → Plugins locais → PlayerGames** para configurar chaves de API e parâmetros de temporada.
6. Crie a primeira temporada na página **Gerenciar Temporadas**.

---

### 📖 Como Usar

#### Para Administradores

1. Instale o plugin e conclua o upgrade do Moodle.
2. Configure as chaves de API globais em *Administração do site → Plugins → Plugins locais → PlayerGames → Chaves de API*.
3. Crie a primeira temporada na página **Gerenciar Temporadas**, definindo nome, datas e as recompensas de XP por jogo e jogadas por dia.
4. Opcionalmente ajuste a página **Escada de Níveis** — altere os limiares de XP e os títulos, restaure a escada padrão ou gere uma linear mais longa.
5. Faça upload ou gere um cartucho de conteúdo na página **Cartuchos**.
6. Acompanhe o impacto da gamificação no **Engajômetro**.

#### Para Professores

1. Acesse **Minhas Chaves de IA** para configurar chaves pessoais (opcional — as chaves globais funcionam se configuradas pelo admin).
2. Use o hub PlayerGames ou qualquer plugin Player — os recursos de IA usarão automaticamente a cadeia de chaves configurada.

#### Para Estudantes

1. Acesse o **Player Hub** para ver seu XP, nível, posição no ranking, streak e missões do dia.
2. Jogue os minijogos diários para ganhar XP.
3. Marque ou desmarque "Aparecer no ranking" no seu perfil para controlar sua visibilidade.

---

### 🧪 Testes Automatizados

O PlayerGames acompanha uma suíte PHPUnit que cobre o motor de gamificação, o pipeline de cartuchos, as tarefas agendadas, a privacidade e os eventos. Cada push no CI roda a matriz completa (Moodle 4.5 → 5.2, PostgreSQL e MariaDB).

#### PHPUnit — Testes de Unidade e Integração

| Arquivo de teste | Casos | O que é coberto |
|------------------|------:|-----------------|
| `cartridge/importer_test.php` | 9 | Importação de conceito e quiz; inferência de tipo; clamp de dificuldade; dedup de categoria; erros de schema |
| `cartridge/exporter_test.php` | 4 | Estrutura do export concept/quiz e round-trip completo import→export incluindo metadados de raiz |
| `cartridge/category_manager_test.php` | 7 | CRUD de categoria, sortorder incremental, `ensure` idempotente, checagem de posse, conceito null ao excluir |
| `cartridge/quiz_generator_test.php` | 7 | Parsing da resposta de quiz, defaults de categoria/dificuldade, persistência em `save_standalone` |
| `cartridge/ai_generator_test.php` | 5 | Parser de conceitos: JSON encapsulado/puro/com fences, JSON inválido e ausência de conceitos |
| `hub/xp_manager_test.php` | 7 | Limiares de nível, cap por jogo, recompensa de missão sem cap, evento de subida de nível |
| `hub/daily_play_manager_test.php` | 3 | Múltiplas jogadas dividem o XP igualmente; jogada além da cota diária rejeitada; última jogada aparada no cap exato |
| `hub/level_manager_test.php` | 8 | Seed da escada, busca de XP/título, save com renumeração + piso zero, restaurar padrão, geração linear e limites |
| `hub/checkin_manager_test.php` | 9 | Insert do check-in + concessão de XP, idempotência, cap de temporada, avanço opcional de streak, elegibilidade de participante |
| `hub/served_questions_test.php` | 3 | Conjunto "já exibido" do dia agrupado por fonte, escopo idempotente, itens inválidos ignorados |
| `hub/streak_manager_test.php` | 9 | Início/continuação/reset de streak, consumo de freeze, processamento de quebra |
| `hub/season_manager_test.php` | 8 | Ciclo da temporada, resolução ativa/próxima, ativação exclusiva, snapshot, `create_next` |
| `hub/mission_manager_test.php` | 7 | Sync de missão, progresso/conclusão com recompensa de XP, resets diário e de check-in perdido |
| `hub/achievement_manager_test.php` | 6 | Sync de conquista, concessão (primeiro jogo/nível/todos os jogos no dia), idempotência |
| `hub/title_manager_test.php` | 2 | Clamp da chave nível→título e tradução |
| `observer_test.php` | 2 | `game_completed` aciona streak/missão/conquista; registra streak mesmo sem temporada |
| `games/quiz_loader_test.php` | 10 | Fonte de cartucho: filtro de completude, tamanho da sessão, só-ativos, filtro por id, metadados, sorteio aleatório, exclusão de questões já vistas e reuso do pool |
| `games/season_game_config_test.php` | 7 | Helpers de fonte; busca do registro habilitado; listagem por temporada; seed de defaults e preservação |
| `task/assign_daily_games_test.php` | 3 | Atribuição de conceito por jogo, idempotência, caso sem cartucho |
| `task/reset_daily_missions_test.php` | 1 | Orquestração do reset diário de missões + quebra de streak |
| `task/close_expired_seasons_test.php` | 2 | Fecha temporada expirada; auto-renovação cria e ativa a próxima |
| `task/purge_old_scores_test.php` | 2 | Purga pela janela de retenção; no-op dentro da janela |
| `privacy/provider_test.php` | 8 | Metadata, contextos, userlist, export e as três rotas de deleção |
| `api_key_helper_test.php` | 5 | Resolução de chave pessoal/site, defaults do OpenAI, `has_any_key` |
| `local/engagement_report_test.php` | 4 | Métricas vazias, contagem de cursos, detecção de curso Player, divisão por escopo |
| `ecosystem/plugin_registry_test.php` | 2 | Estrutura do catálogo e componentes únicos |
| `ecosystem/plugin_status_test.php` | 1 | Status de instalação por componente; hub reportado como instalado |
| `event/events_test.php` | 1 | Os nove eventos disparam, são capturados e renderizam descrição |
| **Total** | **142** | |

```bash
vendor/bin/phpunit --testsuite local_playergames
```

---

### 🔎 Divulgação de Serviço de Terceiros

O PlayerGames inclui recursos opcionais de geração de conteúdo via IA (cartuchos). Esses recursos são totalmente opcionais — todo o conteúdo pode ser criado manualmente.

#### Provedores suportados

- **Google Gemini** — https://ai.google.dev/
- **Groq** — https://console.groq.com/
- **APIs compatíveis com OpenAI** — Qualquer provedor que siga o formato da API OpenAI (ex.: OpenRouter, modelos locais via LM Studio, proxy Ollama, etc.)

Esses serviços seguem seus próprios termos de uso e políticas de privacidade.

#### Transmissão de dados

Quando o recurso de IA é utilizado, os prompts informados são enviados ao provedor selecionado para processamento. O plugin:
- Não armazena prompts nem respostas da IA
- Apenas salva os conceitos do cartucho criados dentro do Moodle

Nenhuma comunicação externa ocorre sem ativação explícita de um recurso de IA.

---

## 📄 Licença

Este projeto é licenciado sob a **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio
