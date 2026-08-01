# ✨ Funcionalidades

## ✅ Implementado

* 🗺️ **Dashboard do Ecossistema:** Visão SVG de todos os plugins Player — instalados, ausentes,
  dependências, status e links de ação rápida para admins.
* 🎮 **Player Hub — XP e Níveis:** XP site-wide ao longo de temporadas configuráveis. Cada
  minijogo concede uma quantidade fixa de XP, e o admin define quantas jogadas pontuáveis por dia
  cada jogo permite — o XP é ganho exclusivamente jogando, então professores com diferentes
  cargas de turmas e estudantes matriculados em quantidades diferentes de cursos competem em
  igualdade de condições.
* 🪜 **Player Hub — Escada de Níveis Configurável:** O admin edita os limiares de XP e os
  títulos de cada nível na página Escada de Níveis — restaura a escada padrão de 5 faixas ou gera
  uma progressão linear mais longa com um clique.
* 📅 **Player Hub — Check-in Diário:** Ganhe XP apenas por acessar o hub uma vez ao dia, com
  limite por temporada. Opcionalmente conta para o streak diário.
* 🏆 **Player Hub — Ranking de Temporada:** Ranking com controle de privacidade (opt-in),
  desempate e separação por staff/estudante — ver [Como o Ranking Funciona](#ranking).
* 📘 **Player Hub — XP de Aprendizado:** Um pool de XP opcional e separado, espelhado da
  atividade do estudante por curso no `block_playerhud`, com seu próprio ranking opt-in — ver
  [XP de Aprendizado](#learning-xp).
* 🔥 **Player Hub — Streak e Freeze:** Acompanhamento de streak diário. Consumíveis de freeze
  evitam a perda de streak e são ganhos via missões; o check-in diário pode manter o streak vivo
  quando configurado.
* 🎯 **Player Hub — Missões:** Missões diárias, por streak, cumulativas de XP e por vitória, com
  recompensas de XP configuráveis.
* 🏅 **Player Hub — Conquistas:** Conquistas permanentes que persistem entre temporadas.
* 🏷️ **Player Hub — Títulos:** Títulos baseados em nível visíveis no perfil Moodle, fóruns e
  cursos.
* 🦊 **Player Hub — Coleção de Avatares:** Avatares emoji desbloqueados permanentemente pelo maior
  nível já alcançado pelo jogador, agrupados em 4 níveis configuráveis — ver
  [Avatares](#avatars).
* 🕐 **Log Unificado de Atividade:** Um único log cronológico de todo evento de XP, streak e
  freeze (XP de temporada, XP de aprendizado, freeze ganho/usado, streak quebrado), com um modal
  de ajuda compartilhado explicando XP de temporada, XP de aprendizado, avatares e rankings.
* 📦 **Sistema de Cartuchos:** Fonte de conteúdo para os minijogos. Múltiplos cartuchos ativos
  simultaneamente.
  * **Todos os jogos:** criação manual (editor inline), upload de JSON ou geração com IA (ver
    [Cadeia de Provedores de IA](#ai-provider-chain)).
  * **PlayerQuiz:** aceita também o **Banco de Questões do Moodle** (apenas questões de múltipla
    escolha).
* 🧠 **PlayerQuiz:** Minijogo diário de múltipla escolha usando conceitos do cartucho ativo — ver
  [Como o PlayerQuiz Funciona](#playerquiz).
* 🔡 **PlayerGuess:** Minijogo diário estilo Wordle — adivinhe o termo letra a letra — ver
  [Como o PlayerGuess Funciona](#playerguess).
* 📝 **PlayerFill:** Minijogo diário estilo cruzadinha — posições numeradas; o mesmo número
  compartilha a mesma letra entre múltiplos termos; resolver um revela letras nos demais (efeito
  cascata). Grid gerado em PHP sem bibliotecas externas — ver
  [Como o PlayerFill Funciona](#playerfill).
* 🧩 **Bloco PlayerGames:** Bloco sidebar companheiro (`block_playergames`) mostrando o avatar
  equipado, nível, XP, streak, jogos do dia e posição no ranking do usuário na página inicial do
  site e no Painel, com link para o Player Hub completo — ver
  [Ecossistema PlayerGames](#ecosystem).
* 📅 **Gerenciamento de Temporadas:** Criar, fechar e renovar automaticamente temporadas com
  snapshots de configuração. O histórico é preservado ao fechar uma temporada.
* 🔐 **Privacidade (LGPD/GDPR):** Privacy Provider completo — declaração de metadados, export e
  deleção de todos os dados pessoais armazenados; cartuchos compartilhados são preservados com o
  autor anonimizado.
* 🧪 **Testes Automatizados:** Suíte PHPUnit com 246 casos, verde na matriz completa do CI (ver a
  seção [Testes Automatizados](#testing)).

## ⏳ Em Desenvolvimento / Planejado

* ⚔️ **PlayerBattle:** Minijogo match-3 RPG (grid 8×8) com combate por turnos contra um boss,
  movido pelo Phaser 3. Combinar peças de mana carrega uma pergunta do cartucho; acertar → dano
  triplo no boss; errar → o jogador leva dano.
* 📦 **Phaser Centralizado:** O `local_playergames` passará a servir o `phaser.min.js` para todos
  os plugins Player via `local_playergames_get_phaser_url()`, eliminando cópias duplicadas em
  cada plugin.
* 🛡️ **Polimento para Publicação:** Auditoria completa de acessibilidade e cobertura Behat mais
  ampla (a suíte PHPUnit e a conformidade PHPCS já estão prontas).
