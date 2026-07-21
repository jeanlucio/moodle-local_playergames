# 🧠 Como o PlayerQuiz Funciona

O PlayerQuiz é o primeiro mini-jogo diário. Ele sorteia questões de múltipla escolha dos
cartuchos ativos (e, opcionalmente, do Banco de Questões do Moodle) e as exibe **uma de cada
vez**.

## O fluxo da jogada

- Uma questão é exibida por vez, com uma contagem regressiva por pergunta.
- **Erro** (ou o tempo esgotar) → a alternativa correta é revelada e a próxima questão carrega.
- **Acerto** → a jogada termina e o XP é concedido.
- Com um limite de tentativas definido, esgotar as tentativas encerra a jogada em **falha** (sem
  XP) e pode iniciar um cooldown.

Como a jogada termina no **primeiro acerto**, o jogador raramente responde o pool inteiro — a
maioria das questões da sessão é só um colchão que dá variedade e margem para errar antes de
repetir.

## Dois limites independentes (é aqui que muita gente se confunde)

| Configuração | Eixo | Significado |
|---|---|---|
| **Jogos por dia** (`xp_games_quiz`) | o *dia* | Quantas **jogadas** valem XP por dia. Também define o teto diário de XP: `XP por jogo × jogos por dia`. |
| **Questões por sessão** (`quiz_session_size`) | dentro de *uma jogada* | Quantas questões são sorteadas para uma **única jogada** (o "baralho" daquela partida). |

*Exemplo* — com *XP por jogo = 25*, *jogos por dia = 3*, *questões por sessão = 20*: o jogador
pode concluir **3 jogadas** hoje (teto de 75 XP), e **cada** jogada é montada a partir de um
sorteio novo de **20** questões. Os dois números respondem perguntas diferentes: *quantas
partidas hoje* vs *qual o tamanho do baralho de cada partida*.

## Questões novas e repetições

- Cada carregamento da página monta uma **sessão nova e embaralhada** — não existe uma "sessão do
  dia" guardada.
- As questões só são lembradas como "vistas hoje" **quando uma jogada é concluída com acerto**.
  Elas então são excluídas das **outras jogadas do mesmo dia**, então jogadas concluídas nunca
  repetem questões.
- **Sair no meio ou falhar não grava nada** — reabrir a página gera um sorteio novo que pode
  incluir as mesmas questões.
- A memória de "vistas hoje" é **por dia**: o dia seguinte começa limpo e pode re-sortear questões
  de dias anteriores. Não há exclusão entre dias.
- Recarregar nunca dá XP extra — só muda *quais* questões aparecem; o XP continua limitado por
  *jogos por dia* e só conta no acerto.

## Configurações do administrador

*Administração do site → Plugins → Plugins locais → PlayerGames → Regras dos jogos*

| Configuração | Padrão | O que faz |
|---|---|---|
| **Tempo por questão (segundos)** | 120 (2 min) | Contagem regressiva por questão. Chegar a zero conta como erro e avança. `0` desativa o cronômetro. |
| **Máximo de tentativas** | 0 (ilimitado) | Erros (incluindo tempo esgotado) que encerram a jogada sem XP. `0` mantém o comportamento original — o jogo continua até o jogador acertar. |
| **Cooldown ao falhar (minutos)** | 0 | Quando uma jogada termina em falha, o quiz fica bloqueado por estes minutos. Persistido no servidor (recarregar a página **não** contorna). Só faz sentido junto com um limite de tentativas. |
| **Questões por sessão** | 20 | Tamanho do sorteio de cada jogada, escolhido em uma lista com teto (5–50). |

## Como cronômetro, tentativas e cooldown se encaixam

- O tempo esgotar é tratado exatamente como um erro.
- Uma jogada só pode **falhar** quando há um limite de tentativas; com tentativas ilimitadas
  (`0`) o jogo entra em loop até o acerto, então o cooldown nunca dispara.
- Uma jogada falha **não dá XP e não consome jogada do dia** — o cooldown é o único freio, então
  com cooldown `0` o jogador pode tentar de novo na hora.

## Por que o tamanho da sessão tem teto

Toda questão da sessão é renderizada no HTML da página de uma vez (o navegador apenas vai
revelando uma a uma). Uma sessão muito grande formataria e enviaria centenas de questões que o
jogador nunca verá — trabalho de servidor desperdiçado e página pesada. A lista com teto (máx.
50) mantém variedade de sobra sem esse custo. Sessões realmente grandes exigiriam outra
arquitetura (carregar questões sob demanda), o que não é como o jogo funciona hoje.
