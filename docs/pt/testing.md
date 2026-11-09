# 🧪 Testes Automatizados

O PlayerGames acompanha uma suíte PHPUnit que cobre o motor de gamificação, o pipeline de
cartuchos, as tarefas agendadas, a privacidade e os eventos, e testes em Node para as regras do
PlayerBattle. Cada push no CI roda a matriz
completa (Moodle 4.5 → 5.3, PostgreSQL e MariaDB).

## PHPUnit — Testes de Unidade e Integração

| Arquivo de teste | Casos | O que é coberto |
|------------------|------:|-----------------|
| `cartridge/importer_test.php` | 14 | Importação de conceito e quiz; inferência de tipo; clamp de dificuldade; dedup de categoria; erros de schema; campos de tipo errado; a importação inteira salva em uma só transação |
| `cartridge/exporter_test.php` | 5 | Estrutura do export concept/quiz e round-trip completo import→export incluindo metadados de raiz |
| `cartridge/category_manager_test.php` | 7 | CRUD de categoria, sortorder incremental, `ensure` idempotente, checagem de posse, conceito null ao excluir |
| `cartridge/quiz_generator_test.php` | 12 | Parsing da resposta de quiz, defaults de categoria/dificuldade, persistência em `save_standalone`; respostas com campos de tipo errado |
| `cartridge/ai_generator_test.php` | 7 | Parser de conceitos: JSON encapsulado/puro/com fences, JSON inválido e ausência de conceitos; delegação à fachada do AI Hub com fallback para `core_ai`; respostas com campos de tipo errado |
| `cartridge/payload_test.php` | 3 | Limpeza dos campos importados e devolvidos pela IA: um escalar vira texto e qualquer outra coisa conta como ausente; um valor único vira lista; números são limitados com um valor padrão |
| `hub/xp_manager_test.php` | 17 | Limiares de nível, cap por jogo, recompensa de missão sem cap, evento de subida de nível, posição/desempate no ranking de temporada |
| `hub/avatar_manager_test.php` | 10 | Seed do catálogo padrão, limiares de faixa, rastreio do melhor nível vitalício, regras de desbloqueio/equipar, rejeição de equipar avatar bloqueado |
| `hub/learning_xp_manager_test.php` | 22 | Blocos mensais com janela, controle de visibilidade (independente do status de staff), opt-in e posição no ranking, correção no recálculo |
| `hub/daily_play_manager_test.php` | 6 | Múltiplas jogadas dividem o XP igualmente; jogada além da cota diária rejeitada; última jogada aparada no cap exato; o payload extra (vitória) chega ao evento e só completa a missão da batalha quando marcado; todo jogo pendente aponta para a sua página e um já jogado não |
| `hub/level_manager_test.php` | 8 | Seed da escada, busca de XP/título, save com renumeração + piso zero, restaurar padrão, geração linear e limites |
| `hub/checkin_manager_test.php` | 11 | Insert do check-in + concessão de XP, idempotência, cap de temporada, avanço opcional de streak, elegibilidade de participante |
| `hub/served_questions_test.php` | 3 | Conjunto "já exibido" do dia agrupado por fonte, escopo idempotente, itens inválidos ignorados |
| `hub/streak_manager_test.php` | 12 | Início/continuação/reset de streak, consumo de freeze, processamento de quebra |
| `hub/season_manager_test.php` | 15 | Ciclo da temporada, resolução ativa/próxima, ativação exclusiva, snapshot, `create_next`; se existe alguma temporada; edição (a encerrada mantém as datas), exclusão só dos dados da própria temporada, a ativa recusada |
| `hub/mission_manager_test.php` | 7 | Sync de missão, progresso/conclusão com recompensa de XP, resets diário e de check-in perdido |
| `hub/achievement_manager_test.php` | 6 | Sync de conquista, concessão (primeiro jogo/nível/todos os jogos no dia), idempotência |
| `hub/title_manager_test.php` | 2 | Clamp da chave nível→título e tradução |
| `observer_test.php` | 5 | `game_completed` aciona streak/missão/conquista; registra streak mesmo sem temporada; espelha mudanças de XP do `block_playerhud` no XP de Aprendizado |
| `hook_callbacks_test.php` | 5 | Link na navegação principal: o hub para o estudante, nenhum depois de desativar a gamificação, o dashboard para o professor que a desativou, nenhum para visitante, e a consulta de equipe docente dispensada (contada em consultas) quando a gamificação está ligada |
| `games/quiz_loader_test.php` | 13 | Fonte de cartucho: filtro de completude, tamanho da sessão, só-ativos, filtro por id, metadados, sorteio aleatório, exclusão de questões já vistas e reuso do pool; o banco de questões lê só uma categoria mantida para reuso |
| `games/quiz_settings_test.php` | 8 | Configuração de cronômetro, máximo de tentativas e cooldown, e como interagem |
| `games/guess_manager_test.php` | 7 | Resolução do conceito diário, normalização do termo, feedback de letras estilo Wordle (incluindo letras duplicadas), validação da tentativa |
| `games/fill_manager_test.php` | 10 | Clamp da config de quantidade de palavras/tentativas máximas, atribuição de letra→slot compartilhado (incluindo termos sem letras em comum), estado de revelação das casas, cascata de revelação cruzada, ordenação/filtro dos termos do dia, regras de revelação no payload de resposta do POST |
| `games/battle_manager_test.php` | 13 | PlayerBattle no servidor: questões entregues prontas para o navegador com o texto escapado uma vez, nenhuma batalha sem questões, derrota salva sem XP e sem gastar a jogada, vitória com o XP da temporada (e sem XP além da cota), o melhor resultado do dia, batalha aceita uma só vez, contadores dentro do limite, só as questões entregues contam como vistas, o oponente do dia |
| `games/season_game_config_test.php` | 7 | Helpers de fonte; busca do registro habilitado; listagem por temporada; seed de defaults e preservação |
| `external/set_avatar_test.php` | 3 | Equipar/desequipar via o endpoint AJAX, rejeitando um avatar bloqueado |
| `external/set_ranking_visibility_test.php` | 2 | Toggle de opt-in/opt-out do ranking de temporada |
| `external/set_learning_ranking_visibility_test.php` | 2 | Toggle de opt-in/opt-out do ranking de XP de Aprendizado |
| `task/assign_daily_games_test.php` | 8 | Atribuição de conceito por jogo, atribuição multi-conceito do PlayerFill e o caso de pool insuficiente, idempotência, casos sem cartucho e com cartucho vazio, filtro de elegibilidade do PlayerGuess, nome da tarefa |
| `task/reset_daily_missions_test.php` | 1 | Orquestração do reset diário de missões + quebra de streak |
| `task/close_expired_seasons_test.php` | 2 | Fecha temporada expirada; auto-renovação cria e ativa a próxima |
| `task/purge_old_scores_test.php` | 3 | Purga pela janela de retenção; no-op dentro da janela; os resultados de batalha das temporadas fora da janela também são purgados |
| `task/recompute_learning_xp_test.php` | 2 | Recálculo noturno da janela descarta blocos vencidos e corrige desvio de cache |
| `privacy/provider_test.php` | 9 | Metadata, contextos, userlist, export e as três rotas de deleção; toda tabela declarada lista exatamente as suas colunas reais |
| `output/hub_test.php` | 2 | Quem recebe o atalho para a página de temporadas enquanto não há temporada: o administrador, e mais ninguém |
| `local/access_test.php` | 16 | Detecção de staff (admin de site, capacidade de edição de curso), resolução em lote de ids de staff, regras de visibilidade do hub; para onde cada papel é levado |
| `local/admin_cards_test.php` | 8 | Aba Administração: cards e grupos por permissão (administrador, gerente, estudante), a aba na navegação, o estado de cada card, nome de temporada escapado uma vez e o link da documentação abrindo em nova aba |
| `local/game_page_test.php` | 2 | O aviso das páginas de jogo quando não há temporada ativa, e que ele nunca diz que o jogo já foi jogado |
| `local/question_bank_reuse_test.php` | 4 | Quais categorias do banco de questões são mantidas para reuso (bancos compartilhados no Moodle 5.x, contexto do sistema no 4.5), contagem de questões, o que o usuário pode escolher e a conferência de uma categoria dada |
| `cartridge/glossary_importer_test.php` | 4 | Glossários globais listados com as entradas aprovadas, importação para um cartucho de conceitos, recusa de glossário de curso e de glossário vazio |
| `local/text_test.php` | 4 | Nomes em texto plano: texto como digitado, o título do nível, páginas de cartucho escapando os nomes uma vez e formulários de edição preenchendo os campos com o texto armazenado |
| `local/engagement_report_test.php` | 4 | Métricas vazias, contagem de cursos, detecção de curso Player, divisão por escopo |
| `ecosystem/plugin_registry_test.php` | 2 | Estrutura do catálogo e componentes únicos |
| `ecosystem/plugin_status_test.php` | 1 | Status de instalação por componente; hub reportado como instalado |
| `event/events_test.php` | 1 | Os nove eventos disparam, são capturados e renderizam descrição |
| `db_upgrade_test.php` | 4 | Passo de upgrade que remove um índice que repete uma chave: dois índices idênticos viram um, uma coluna sem índice recebe o índice da chave, um índice único é mantido; os jogos de conceito perdem a fonte glossário e a categoria |
| `uninstall_test.php` | 2 | A desinstalação remove só as preferências de usuário do próprio plugin, pelo prefixo, e não faz nada quando não há nenhuma |
| **Total** | **321** | |

```bash
vendor/bin/phpunit --testsuite local_playergames
```

## Node — Regras do PlayerBattle

As regras do tabuleiro e do combate do PlayerBattle são módulos simples (`amd/src/battle/`) que também
carregam no Node, então são testadas sem navegador nem Phaser. O CI as roda com `node --test`.

| Arquivo de teste | Casos | O que é coberto |
|------------------|------:|-----------------|
| `js/battle/prng.test.js` | 5 | O gerador com semente: mesma semente, mesma sequência; sementes diferentes diferem; valores em [0, 1); uma sequência conhecida e fixa; sementes convertidas para 32 bits sem sinal |
| `js/battle/board_rules.test.js` | 14 | Uma grade nova só tem os tipos em jogo e nenhuma combinação; o número de tipos é o pedido; combinações achadas com o tamanho; uma peça em duas combinações é destruída uma vez; o tipo 0 é uma peça, não uma célula vazia; trocas; que tipo uma troca combinaria; a jogada do oponente; embaralhamento; gravidade e reposição |
| `js/battle/combat_rules.test.js` | 23 | Estado inicial e o balanceamento fixado; bônus de combo; dano das Espadas e o multiplicador; Poção; os medidores de Escudo e Grimório; o Orbe enchendo a mana; o golpe crítico e o custo de uma resposta errada; ticks de veneno; vitória e derrota; as regras nunca alteram o estado recebido |
| **Total** | **42** | |

```bash
node --test tests/js/battle/*.test.js
```

## Cobertura

A cobertura de linhas/métodos é medida localmente com Xdebug (`moodle-coverage`, ferramenta de
bancada — não faz parte do CI), em vez de publicada como um percentual único do plugin inteiro:
este código deliberadamente não testa unitariamente os renderers de saída do próprio Moodle
(`classes/output/*`), exceto os dois que decidem quem vê o quê (o botão de temporadas do hub e a aba
Administração), o controlador de UI de gestão de cartuchos, nem as chamadas HTTP reais do
`ai_generator` (só a lógica de parsing da resposta é testada, já que mockar o round-trip de rede
tem ROI baixo) — nada disso é uma lacuna que a suíte PHPUnit deveria fechar, então um número bruto
do plugin inteiro só mediria essa decisão de escopo, não a qualidade dos testes. As classes que a
suíte deveria cobrir — gerenciadores de jogo, serviços do hub, tarefas agendadas, eventos, funções
externas e o Privacy Provider — são o que os casos contados na tabela acima exercitam, e
`games/fill_manager` e `task/assign_daily_games` (as classes que o PlayerFill criou ou alterou)
estão ambas em 100% de cobertura de linhas e métodos.

## Behat — Testes de Aceitação

| Arquivo de feature | Cenários | O que é coberto |
|--------------------|--------:|-----------------|
| `play_quiz.feature` | 2 | O fluxo de jogada do PlayerQuiz de ponta a ponta |

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --tags=@local_playergames --profile=chrome
```
