# 🧪 Testes Automatizados

O PlayerGames acompanha uma suíte PHPUnit que cobre o motor de gamificação, o pipeline de
cartuchos, as tarefas agendadas, a privacidade e os eventos. Cada push no CI roda a matriz
completa (Moodle 4.5 → 5.2, PostgreSQL e MariaDB).

## PHPUnit — Testes de Unidade e Integração

| Arquivo de teste | Casos | O que é coberto |
|------------------|------:|-----------------|
| `cartridge/importer_test.php` | 10 | Importação de conceito e quiz; inferência de tipo; clamp de dificuldade; dedup de categoria; erros de schema |
| `cartridge/exporter_test.php` | 5 | Estrutura do export concept/quiz e round-trip completo import→export incluindo metadados de raiz |
| `cartridge/category_manager_test.php` | 7 | CRUD de categoria, sortorder incremental, `ensure` idempotente, checagem de posse, conceito null ao excluir |
| `cartridge/quiz_generator_test.php` | 10 | Parsing da resposta de quiz, defaults de categoria/dificuldade, persistência em `save_standalone` |
| `cartridge/ai_generator_test.php` | 5 | Parser de conceitos: JSON encapsulado/puro/com fences, JSON inválido e ausência de conceitos; delegação à fachada do AI Hub com fallback para `core_ai` |
| `hub/xp_manager_test.php` | 17 | Limiares de nível, cap por jogo, recompensa de missão sem cap, evento de subida de nível, posição/desempate no ranking de temporada |
| `hub/avatar_manager_test.php` | 10 | Seed do catálogo padrão, limiares de faixa, rastreio do melhor nível vitalício, regras de desbloqueio/equipar, rejeição de equipar avatar bloqueado |
| `hub/learning_xp_manager_test.php` | 22 | Blocos mensais com janela, controle de visibilidade (independente do status de staff), opt-in e posição no ranking, correção no recálculo |
| `hub/daily_play_manager_test.php` | 3 | Múltiplas jogadas dividem o XP igualmente; jogada além da cota diária rejeitada; última jogada aparada no cap exato |
| `hub/level_manager_test.php` | 8 | Seed da escada, busca de XP/título, save com renumeração + piso zero, restaurar padrão, geração linear e limites |
| `hub/checkin_manager_test.php` | 11 | Insert do check-in + concessão de XP, idempotência, cap de temporada, avanço opcional de streak, elegibilidade de participante |
| `hub/served_questions_test.php` | 3 | Conjunto "já exibido" do dia agrupado por fonte, escopo idempotente, itens inválidos ignorados |
| `hub/streak_manager_test.php` | 12 | Início/continuação/reset de streak, consumo de freeze, processamento de quebra |
| `hub/season_manager_test.php` | 8 | Ciclo da temporada, resolução ativa/próxima, ativação exclusiva, snapshot, `create_next` |
| `hub/mission_manager_test.php` | 7 | Sync de missão, progresso/conclusão com recompensa de XP, resets diário e de check-in perdido |
| `hub/achievement_manager_test.php` | 6 | Sync de conquista, concessão (primeiro jogo/nível/todos os jogos no dia), idempotência |
| `hub/title_manager_test.php` | 2 | Clamp da chave nível→título e tradução |
| `observer_test.php` | 5 | `game_completed` aciona streak/missão/conquista; registra streak mesmo sem temporada; espelha mudanças de XP do `block_playerhud` no XP de Aprendizado |
| `games/quiz_loader_test.php` | 12 | Fonte de cartucho: filtro de completude, tamanho da sessão, só-ativos, filtro por id, metadados, sorteio aleatório, exclusão de questões já vistas e reuso do pool |
| `games/quiz_settings_test.php` | 8 | Configuração de cronômetro, máximo de tentativas e cooldown, e como interagem |
| `games/guess_manager_test.php` | 7 | Resolução do conceito diário, normalização do termo, feedback de letras estilo Wordle (incluindo letras duplicadas), validação da tentativa |
| `games/season_game_config_test.php` | 7 | Helpers de fonte; busca do registro habilitado; listagem por temporada; seed de defaults e preservação |
| `external/set_avatar_test.php` | 3 | Equipar/desequipar via o endpoint AJAX, rejeitando um avatar bloqueado |
| `external/set_ranking_visibility_test.php` | 2 | Toggle de opt-in/opt-out do ranking de temporada |
| `external/set_learning_ranking_visibility_test.php` | 2 | Toggle de opt-in/opt-out do ranking de XP de Aprendizado |
| `task/assign_daily_games_test.php` | 4 | Atribuição de conceito por jogo, idempotência, caso sem cartucho, filtro de elegibilidade do PlayerGuess |
| `task/reset_daily_missions_test.php` | 1 | Orquestração do reset diário de missões + quebra de streak |
| `task/close_expired_seasons_test.php` | 2 | Fecha temporada expirada; auto-renovação cria e ativa a próxima |
| `task/purge_old_scores_test.php` | 2 | Purga pela janela de retenção; no-op dentro da janela |
| `task/recompute_learning_xp_test.php` | 2 | Recálculo noturno da janela descarta blocos vencidos e corrige desvio de cache |
| `privacy/provider_test.php` | 8 | Metadata, contextos, userlist, export e as três rotas de deleção |
| `local/access_test.php` | 13 | Detecção de staff (admin de site, capacidade de edição de curso), resolução em lote de ids de staff, regras de visibilidade do hub |
| `local/engagement_report_test.php` | 4 | Métricas vazias, contagem de cursos, detecção de curso Player, divisão por escopo |
| `ecosystem/plugin_registry_test.php` | 2 | Estrutura do catálogo e componentes únicos |
| `ecosystem/plugin_status_test.php` | 1 | Status de instalação por componente; hub reportado como instalado |
| `event/events_test.php` | 1 | Os nove eventos disparam, são capturados e renderizam descrição |
| **Total** | **232** | |

```bash
vendor/bin/phpunit --testsuite local_playergames
```

## Behat — Testes de Aceitação

| Arquivo de feature | Cenários | O que é coberto |
|--------------------|--------:|-----------------|
| `play_quiz.feature` | 2 | O fluxo de jogada do PlayerQuiz de ponta a ponta |

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --tags=@local_playergames --profile=chrome
```
