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
  títulos de cada nível na página **Níveis e títulos** — restaura a escada padrão de 5 faixas ou gera
  uma progressão linear mais longa com um clique.
* 📅 **Player Hub — Check-in Diário:** Ganhe XP apenas por estar no site uma vez ao dia (a primeira
  página que você abre), com limite por temporada. Opcionalmente conta para o streak diário.
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
* 🏷️ **Player Hub — Títulos:** O título de cada nível, exibido no Player Hub.
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
  * **PlayerQuiz e PlayerBattle:** aceitam também um **Banco de Questões do Moodle** mantido para reuso
    por todo o site (apenas questões de múltipla escolha) — ver [Como o PlayerQuiz Funciona](#playerquiz).
  * **PlayerGuess e PlayerFill:** usam sempre cartuchos de conceitos, que também podem ser criados a
    partir de um **glossário global** do site.
* 🧠 **PlayerQuiz:** Minijogo diário de múltipla escolha usando conceitos do cartucho ativo — ver
  [Como o PlayerQuiz Funciona](#playerquiz).
* 🔡 **PlayerGuess:** Minijogo diário estilo Wordle — adivinhe o termo letra a letra — ver
  [Como o PlayerGuess Funciona](#playerguess).
* 📝 **PlayerFill:** Minijogo diário estilo cruzadinha — posições numeradas; o mesmo número
  compartilha a mesma letra entre múltiplos termos; resolver um revela letras nos demais (efeito
  cascata). Grid gerado em PHP sem bibliotecas externas — ver
  [Como o PlayerFill Funciona](#playerfill).
* ⚔️ **PlayerBattle:** Minijogo diário match-3 RPG — tabuleiro 8×8, combate por turnos contra o
  oponente do dia e uma questão sempre que a barra de mana enche; acertar causa um golpe crítico. XP
  só na vitória — ver [Como o PlayerBattle Funciona](#playerbattle).
* 🧭 **Aba Administração:** Cards para tudo o que o administrador configura — cartuchos, temporadas,
  os jogos de uma temporada, as regras — na ordem em que é feito, cada um dizendo o que faz e em que
  ponto está. As páginas do plugin na administração do Moodle seguem a mesma ordem — ver
  [Administração](#administration).
* 🧩 **Bloco PlayerGames:** Bloco sidebar companheiro (`block_playergames`) mostrando o avatar
  equipado, nível, XP, streak, jogos do dia e posição no ranking do usuário na página inicial do
  site e no Painel, com link para o Player Hub completo; enquanto não há temporada ativa, ele avisa e
  diz quando a próxima começa — ver [Ecossistema PlayerGames](#ecosystem).
* 📅 **Gerenciamento de Temporadas:** Criar, fechar e renovar automaticamente temporadas com
  snapshots de configuração. As pontuações das temporadas encerradas mais recentes são mantidas (duas
  por padrão, configurável); a tarefa semanal de retenção remove as mais antigas.
* 🔐 **Privacidade (LGPD/GDPR):** Privacy Provider completo — declaração de metadados, export e
  deleção de todos os dados pessoais armazenados; cartuchos compartilhados são preservados com o
  autor anonimizado.
* 🧪 **Testes Automatizados:** Suíte PHPUnit com 315 casos e 42 testes Node para as regras do
  PlayerBattle, verdes na matriz completa do CI, Moodle 4.5 a 5.3 (ver a seção
  [Testes Automatizados](#testing)).

## ⏳ Em Desenvolvimento / Planejado

* 📦 **Phaser Compartilhado:** O `local_playergames` já empacota o Phaser 4 e o módulo carregador
  `local_playergames/phaser`. Outras atividades Player poderão depender dele em vez de empacotar a
  própria cópia da biblioteca.
* 🛡️ **Polimento para Publicação:** Auditoria completa de acessibilidade (incluindo o tema Classic e
  dispositivos de toque) e cobertura de aceitação Behat mais ampla (a suíte PHPUnit e a conformidade
  com o PHPCS já estão em dia).
