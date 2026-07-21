# 🧪 Testes Automatizados

O PlayerGames acompanha uma suíte PHPUnit que cobre o motor de gamificação, o pipeline de
cartuchos, as tarefas agendadas, a privacidade e os eventos. Cada push no CI roda a matriz
completa (Moodle 4.5 → 5.2, PostgreSQL e MariaDB).

## PHPUnit — Testes de Unidade e Integração

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
