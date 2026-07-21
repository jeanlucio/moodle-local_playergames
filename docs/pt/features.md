# ✨ Funcionalidades

## ✅ Implementado

* 🗺️ **Dashboard do Ecossistema:** Visão SVG de todos os plugins Player — instalados, ausentes,
  dependências, status e links de ação rápida para admins.
* 🔑 **Hub Central de Chaves de IA:** Configure chaves Gemini, Groq e compatíveis com OpenAI uma
  única vez — todos os plugins Player as consomem automaticamente via cadeia de prioridade com
  4 níveis.
* 📊 **Engajômetro:** Compare métricas de engajamento (eventos por aluno, taxa de conclusão, nota
  média) entre cursos com e sem plugins Player — disponível para admins (todos os cursos) e
  professores (apenas os seus).
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
* 🏆 **Player Hub — Ranking:** Ranking por temporada com controle de privacidade (opt-in),
  separado por grupo de participantes (estudantes vs. não-estudantes — professores, managers,
  admins). Admins e managers veem ambos os grupos em abas. Jogadores fora do top 50 ainda veem
  sua própria posição, e empates são desempatados por quem atingiu o XP primeiro.
* 🔥 **Player Hub — Streak e Freeze:** Acompanhamento de streak diário. Consumíveis de freeze
  evitam a perda de streak e são ganhos via missões; o check-in diário pode manter o streak vivo
  quando configurado.
* 🎯 **Player Hub — Missões:** Missões diárias, por streak, cumulativas de XP e por vitória, com
  recompensas de XP configuráveis.
* 🏅 **Player Hub — Conquistas:** Conquistas permanentes que persistem entre temporadas.
* 🏷️ **Player Hub — Títulos:** Títulos baseados em nível visíveis no perfil Moodle, fóruns e
  cursos.
* 📦 **Sistema de Cartuchos:** Fonte de conteúdo para os minijogos. Múltiplos cartuchos ativos
  simultaneamente.
  * **Todos os jogos:** criação manual (editor inline), upload de JSON ou geração com IA
    (Gemini/Groq/OpenAI).
  * **PlayerQuiz e PlayerBattle:** aceitam também o **Banco de Questões do Moodle** (apenas
    questões de múltipla escolha).
  * **PlayerGuess e PlayerFill:** aceitam também o **Glossário do Moodle** (termos e definições
    reaproveitados sem configuração adicional).
* 🧠 **PlayerQuiz:** Minijogo diário de múltipla escolha usando conceitos do cartucho ativo.
  Errou → novo conceito; acertou → XP. Rejogar no mesmo dia traz questões novas em vez de repetir
  as já vistas.
* 📅 **Gerenciamento de Temporadas:** Criar, fechar e renovar automaticamente temporadas com
  snapshots de configuração. O histórico é preservado ao fechar uma temporada.
* 🔐 **Privacidade (LGPD/GDPR):** Privacy Provider completo — declaração de metadados, export e
  deleção de todos os dados pessoais armazenados; cartuchos compartilhados são preservados com o
  autor anonimizado.
* 🧪 **Testes Automatizados:** Suíte PHPUnit com 142 casos, verde na matriz completa do CI (ver a
  seção [Testes Automatizados](#testing)).

## ⏳ Em Desenvolvimento / Planejado

* 🔡 **PlayerGuess:** Minijogo estilo Wordle — adivinhe o termo letra a letra (5 a 8 letras,
  configurável). Seis tentativas antes de revelar a resposta.
* 📝 **PlayerFill:** Minijogo de cruzadinha — posições numeradas; o mesmo número compartilha a
  mesma letra entre múltiplas palavras; resolver uma palavra revela letras nas demais (efeito
  cascata). Grid gerado em PHP sem bibliotecas externas.
* ⚔️ **PlayerBattle:** Minijogo match-3 RPG (grid 8×8) com combate por turnos contra um boss,
  movido pelo Phaser 3. Combinar peças de mana carrega uma pergunta do cartucho; acertar → dano
  triplo no boss; errar → o jogador leva dano.
* 📦 **Phaser Centralizado:** O `local_playergames` passará a servir o `phaser.min.js` para todos
  os plugins Player via `local_playergames_get_phaser_url()`, eliminando cópias duplicadas em
  cada plugin.
* 🧩 **block_playergames:** Bloco sidebar companheiro exibindo XP, nível, streak e status dos
  jogos diários do usuário em qualquer página do Moodle, com link para o Player Hub completo.
* 🛡️ **Polimento para Publicação:** Auditoria completa de acessibilidade e testes de aceitação
  Behat (a suíte PHPUnit e a conformidade PHPCS já estão prontas).
