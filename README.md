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
* 🎮 **Player Hub — XP & Levels:** Site-wide XP and level system with configurable seasons. XP is earned exclusively through mini-games, ensuring fairness for all participants — teachers with different course loads and students enrolled in different numbers of courses all compete on equal footing.
* 🏆 **Player Hub — Ranking:** Season ranking with privacy controls (opt-in), separated by participant group (students vs. non-students — teachers, managers, admins). Admins and managers see both groups via tabs.
* 🔥 **Player Hub — Streak & Freeze:** Daily streak tracking. Freeze consumables prevent streak loss; earned via missions.
* 🎯 **Player Hub — Missions:** Daily, streak-based, cumulative XP, and victory-based missions with configurable XP rewards.
* 🏅 **Player Hub — Achievements:** Permanent achievements that persist across seasons.
* 🏷️ **Player Hub — Titles:** Level-based titles visible in Moodle profiles, forums, and courses.
* 📦 **Cartridge System:** Content source for the mini-games. Supports multiple active cartridges simultaneously.
  * **All games:** manual creation (inline editor), JSON upload, or AI generation (Gemini/Groq/OpenAI).
  * **PlayerQuiz and PlayerBattle:** also accept the **Moodle Question Bank** (multiple-choice questions only).
  * **PlayerGuess and PlayerFill:** also accept the **Moodle Glossary** (terms and definitions reused as-is).
* 🧠 **PlayerQuiz:** Daily multiple-choice mini-game using concepts from the active cartridge. Wrong answer → new concept; correct answer → XP.
* 📅 **Season Management:** Create, close, and auto-renew seasons with configuration snapshots. Historical data is preserved when a season closes.
* 🔐 **Privacy (GDPR):** Complete Privacy Provider — metadata declaration, export and deletion of all stored personal data; shared cartridges are preserved with the uploader anonymised.
* 🧪 **Automated Tests:** 110-case PHPUnit suite, green across the full CI matrix (see the Automated Tests section).

#### ⏳ In Development / Planned

* 🔡 **PlayerGuess:** Wordle-style mini-game — guess the term letter by letter (5–8 letters, configurable). Six attempts before the answer is revealed.
* 📝 **PlayerFill:** Crossword-style mini-game — numbered positions; the same number shares the same letter across multiple words; solving one word reveals letters in others (cascade effect). Grid generated in PHP without external libraries.
* 📅 **Daily Check-in:** Presence mechanic — earn XP simply by accessing the platform once per day, with streak integration and a monthly history view.
* ⚔️ **PlayerBattle:** Match-3 RPG mini-game (8×8 grid) with turn-based combat against a boss powered by Phaser 3. Combining mana pieces charges a question; correct answer → triple damage; wrong answer → player takes damage.
* 📦 **Phaser Centralized:** `local_playergames` will serve `phaser.min.js` to all Player plugins via `local_playergames_get_phaser_url()`, removing duplicated copies from each plugin.
* 🧩 **block_playergames:** Companion sidebar block showing the user's current XP, level, streak, and daily game status on any Moodle page, linking to the full Player Hub.
* 🛡️ **Publication Polish:** Full accessibility audit and Behat acceptance tests (PHPUnit suite and PHPCS compliance are already in place).

---

### 🤖 AI Provider Chain

PlayerGames selects an AI provider using a fixed priority order. The first provider with a working configuration is used; if it fails, the next one is tried automatically.

**Provider selection order:**

| Priority | Provider | Notes |
|----------|----------|-------|
| 1 | **Moodle `core_ai`** | Uses whichever AI providers the site admin configured in *Site administration → AI → AI providers*. On Moodle 5.2+, `core_ai` itself has internal fallback between multiple configured providers. No API key needed in PlayerGames. |
| 2 | **Google Gemini** | Direct API call with JSON output mode enforced. |
| 3 | **Groq** | Direct API call with JSON output mode enforced. |
| 4 | **OpenAI-compatible** | Any provider following the OpenAI `/chat/completions` format. |

**Key resolution per direct provider:**

| Level | Source |
|-------|--------|
| 1 | Teacher's personal key set in PlayerGames (*My AI Keys* page) |
| 2 | Site-wide key set by the admin in PlayerGames settings |
| 3 | Teacher's personal key set in **block_playerhud** (if installed) |
| 4 | Site-wide key set in **block_playerhud** settings (if installed) |

> **Provider order beats key origin.** If a Gemini key exists only at level 4 (PlayerHUD site config) and a Groq key exists at level 2 (PlayerGames site config), Gemini is used because it is tested first.

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
3. Create the first season in the **Season Management** page, setting name, start/end dates, and XP caps.
4. Upload or generate a content cartridge in the **Cartridge** page.
5. Monitor gamification impact in the **Engagement Meter** page.

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
| `hub/xp_manager_test.php` | 7 | Level thresholds, daily cap enforcement, uncapped mission award, level-up event |
| `hub/streak_manager_test.php` | 8 | Streak start/continue/reset, freeze consumption, overnight break processing |
| `hub/season_manager_test.php` | 8 | Season lifecycle, active/upcoming resolution, exclusive activation, snapshot, `create_next` |
| `hub/mission_manager_test.php` | 6 | Mission sync, progress/completion with XP reward, daily and missed check-in resets |
| `hub/achievement_manager_test.php` | 5 | Achievement sync, granting (first game/level/all-games-day), idempotency |
| `hub/title_manager_test.php` | 2 | Level→title key clamping and translation |
| `observer_test.php` | 3 | `game_completed` and `user_loggedin`: streak/mission/achievement/check-in integration |
| `games/quiz_loader_test.php` | 6 | Cartridge source: completeness filter, session size, active-only, id filter, metadata passthrough |
| `games/season_game_config_test.php` | 5 | Source helpers; enabled-record lookup; per-season listing |
| `task/assign_daily_games_test.php` | 3 | Per-game concept assignment, idempotency, no-cartridge case |
| `task/reset_daily_missions_test.php` | 1 | Daily mission reset + streak break orchestration |
| `task/close_expired_seasons_test.php` | 2 | Closes expired season; auto-renew creates and activates next |
| `task/purge_old_scores_test.php` | 2 | Retention-window purge; keep-within-window no-op |
| `privacy/provider_test.php` | 8 | Metadata, contexts, userlist, export, and the three deletion paths |
| `api_key_helper_test.php` | 4 | Personal/site key resolution, OpenAI defaults, `has_any_key` |
| `local/engagement_report_test.php` | 4 | Empty metrics, course counting, player-course detection, scope split |
| `ecosystem/plugin_registry_test.php` | 2 | Catalog structure and unique components |
| `ecosystem/plugin_status_test.php` | 1 | Installed status keyed by component; hub reported installed |
| `event/events_test.php` | 1 | All nine events trigger, are captured and render a description |
| **Total** | **110** | |

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
* 🎮 **Player Hub — XP e Níveis:** Sistema de XP e níveis site-wide com temporadas configuráveis. O XP é ganho exclusivamente por minijogos, garantindo equidade para todos os participantes — professores com diferentes cargas de turmas e estudantes matriculados em quantidades diferentes de cursos competem em igualdade de condições.
* 🏆 **Player Hub — Ranking:** Ranking por temporada com controle de privacidade (opt-in), separado por grupo de participantes (estudantes vs. não-estudantes — professores, managers, admins). Admins e managers veem ambos os grupos em abas.
* 🔥 **Player Hub — Streak e Freeze:** Acompanhamento de streak diário. Consumíveis de freeze evitam a perda de streak; ganhos via missões.
* 🎯 **Player Hub — Missões:** Missões diárias, por streak, cumulativas de XP e por vitória, com recompensas de XP configuráveis.
* 🏅 **Player Hub — Conquistas:** Conquistas permanentes que persistem entre temporadas.
* 🏷️ **Player Hub — Títulos:** Títulos baseados em nível visíveis no perfil Moodle, fóruns e cursos.
* 📦 **Sistema de Cartuchos:** Fonte de conteúdo para os minijogos. Múltiplos cartuchos ativos simultaneamente.
  * **Todos os jogos:** criação manual (editor inline), upload de JSON ou geração com IA (Gemini/Groq/OpenAI).
  * **PlayerQuiz e PlayerBattle:** aceitam também o **Banco de Questões do Moodle** (apenas questões de múltipla escolha).
  * **PlayerGuess e PlayerFill:** aceitam também o **Glossário do Moodle** (termos e definições reaproveitados sem configuração adicional).
* 🧠 **PlayerQuiz:** Minijogo diário de múltipla escolha usando conceitos do cartucho ativo. Errou → novo conceito; acertou → XP.
* 📅 **Gerenciamento de Temporadas:** Criar, fechar e renovar automaticamente temporadas com snapshots de configuração. O histórico é preservado ao fechar uma temporada.
* 🔐 **Privacidade (LGPD/GDPR):** Privacy Provider completo — declaração de metadados, export e deleção de todos os dados pessoais armazenados; cartuchos compartilhados são preservados com o autor anonimizado.
* 🧪 **Testes Automatizados:** Suíte PHPUnit com 110 casos, verde na matriz completa do CI (ver a seção Testes Automatizados).

#### ⏳ Em Desenvolvimento / Planejado

* 🔡 **PlayerGuess:** Minijogo estilo Wordle — adivinhe o termo letra a letra (5 a 8 letras, configurável). Seis tentativas antes de revelar a resposta.
* 📝 **PlayerFill:** Minijogo de cruzadinha — posições numeradas; o mesmo número compartilha a mesma letra entre múltiplas palavras; resolver uma palavra revela letras nas demais (efeito cascata). Grid gerado em PHP sem bibliotecas externas.
* 📅 **Check-in Diário:** Mecânica de presença — ganhe XP simplesmente por acessar a plataforma uma vez ao dia, com integração ao streak e histórico mensal.
* ⚔️ **PlayerBattle:** Minijogo match-3 RPG (grid 8×8) com combate por turnos contra um boss, movido pelo Phaser 3. Combinar peças de mana carrega uma pergunta do cartucho; acertar → dano triplo no boss; errar → o jogador leva dano.
* 📦 **Phaser Centralizado:** O `local_playergames` passará a servir o `phaser.min.js` para todos os plugins Player via `local_playergames_get_phaser_url()`, eliminando cópias duplicadas em cada plugin.
* 🧩 **block_playergames:** Bloco sidebar companheiro exibindo XP, nível, streak e status dos jogos diários do usuário em qualquer página do Moodle, com link para o Player Hub completo.
* 🛡️ **Polimento para Publicação:** Auditoria completa de acessibilidade e testes de aceitação Behat (a suíte PHPUnit e a conformidade PHPCS já estão prontas).

---

### 🤖 Cadeia de Provedores de IA

O PlayerGames seleciona um provedor de IA seguindo uma ordem de prioridade fixa. O primeiro provedor com configuração disponível é utilizado; se falhar, o próximo é tentado automaticamente.

**Ordem de seleção de provedor:**

| Prioridade | Provedor | Observações |
|------------|----------|-------------|
| 1 | **Moodle `core_ai`** | Usa os provedores configurados pelo admin em *Administração do site → IA → Provedores de IA*. No Moodle 5.2+, o `core_ai` já tem fallback interno entre múltiplos provedores. Nenhuma chave precisa ser cadastrada no PlayerGames. |
| 2 | **Google Gemini** | Chamada direta com modo de saída JSON forçado. |
| 3 | **Groq** | Chamada direta com modo de saída JSON forçado. |
| 4 | **OpenAI-compatível** | Qualquer provedor que siga o formato `/chat/completions` da OpenAI. |

**Resolução de chave por provedor direto:**

| Nível | Origem |
|-------|--------|
| 1 | Chave pessoal do professor cadastrada no PlayerGames (página *Minhas Chaves de IA*) |
| 2 | Chave global cadastrada pelo admin nas configurações do PlayerGames |
| 3 | Chave pessoal do professor cadastrada no **block_playerhud** (se instalado) |
| 4 | Chave global cadastrada pelo admin nas configurações do **block_playerhud** (se instalado) |

> **A ordem do provedor prevalece sobre a origem da chave.** Se uma chave Gemini existe apenas no nível 4 (config global do PlayerHUD) e uma chave Groq existe no nível 2 (config global do PlayerGames), o Gemini é utilizado porque é testado primeiro.

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
3. Crie a primeira temporada na página **Gerenciar Temporadas**, definindo nome, datas e caps de XP.
4. Faça upload ou gere um cartucho de conteúdo na página **Cartuchos**.
5. Acompanhe o impacto da gamificação no **Engajômetro**.

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
| `hub/xp_manager_test.php` | 7 | Limiares de nível, cap diário, recompensa de missão sem cap, evento de subida de nível |
| `hub/streak_manager_test.php` | 8 | Início/continuação/reset de streak, consumo de freeze, processamento de quebra |
| `hub/season_manager_test.php` | 8 | Ciclo da temporada, resolução ativa/próxima, ativação exclusiva, snapshot, `create_next` |
| `hub/mission_manager_test.php` | 6 | Sync de missão, progresso/conclusão com recompensa de XP, resets diário e de check-in perdido |
| `hub/achievement_manager_test.php` | 5 | Sync de conquista, concessão (primeiro jogo/nível/todos os jogos no dia), idempotência |
| `hub/title_manager_test.php` | 2 | Clamp da chave nível→título e tradução |
| `observer_test.php` | 3 | `game_completed` e `user_loggedin`: integração streak/missão/conquista/check-in |
| `games/quiz_loader_test.php` | 6 | Fonte de cartucho: filtro de completude, tamanho da sessão, só-ativos, filtro por id, metadados |
| `games/season_game_config_test.php` | 5 | Helpers de fonte; busca do registro habilitado; listagem por temporada |
| `task/assign_daily_games_test.php` | 3 | Atribuição de conceito por jogo, idempotência, caso sem cartucho |
| `task/reset_daily_missions_test.php` | 1 | Orquestração do reset diário de missões + quebra de streak |
| `task/close_expired_seasons_test.php` | 2 | Fecha temporada expirada; auto-renovação cria e ativa a próxima |
| `task/purge_old_scores_test.php` | 2 | Purga pela janela de retenção; no-op dentro da janela |
| `privacy/provider_test.php` | 8 | Metadata, contextos, userlist, export e as três rotas de deleção |
| `api_key_helper_test.php` | 4 | Resolução de chave pessoal/site, defaults do OpenAI, `has_any_key` |
| `local/engagement_report_test.php` | 4 | Métricas vazias, contagem de cursos, detecção de curso Player, divisão por escopo |
| `ecosystem/plugin_registry_test.php` | 2 | Estrutura do catálogo e componentes únicos |
| `ecosystem/plugin_status_test.php` | 1 | Status de instalação por componente; hub reportado como instalado |
| `event/events_test.php` | 1 | Os nove eventos disparam, são capturados e renderizam descrição |
| **Total** | **110** | |

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
