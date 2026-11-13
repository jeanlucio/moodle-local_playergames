# 🔡 Como o PlayerGuess Funciona

O PlayerGuess é um minijogo diário estilo Wordle: adivinhe um termo do cartucho ativo, letra por
letra, dentro de um número limitado de tentativas.

## O fluxo da jogada

- O tabuleiro mostra a **definição** do termo (como dica) e seu comprimento, mas não o termo em
  si.
- Cada tentativa deve ter exatamente o mesmo comprimento do termo alvo e conter apenas letras.
- Após cada tentativa, cada letra é marcada como **correta** (letra certa, posição certa),
  **presente** (letra certa, posição errada) ou **ausente** — o clássico feedback do Wordle,
  calculado no servidor para que o termo nunca seja exposto ao navegador antes do fim da rodada.
- **Acerto** → a rodada termina em vitória e o XP é concedido.
- **Esgotar as tentativas** → a rodada termina em derrota e o termo é revelado.
- A validação da tentativa sempre acontece no servidor contra o conceito atribuído do dia — o
  cliente nunca recebe nem confia em nada sobre o termo alvo antes do fim da rodada.

## Um conceito por dia, pipeline de atribuição compartilhado

Toda noite, uma tarefa agendada atribui um conceito aleatório ao PlayerGuess (e, de forma
independente, ao PlayerQuiz) a partir dos cartuchos ativos no momento — o mesmo pipeline usado
entre os minijogos diários. Especificamente para o PlayerGuess, apenas conceitos cujo termo:

- tenha comprimento normalizado dentro da faixa configurada pelo admin (padrão 4–8 letras), e
- seja uma única palavra alfabética — sem espaços, dígitos ou hífens

são elegíveis. Esse filtro é aplicado de forma idêntica no momento da atribuição e novamente ao
validar uma tentativa, então um termo que até se encaixa na faixa de comprimento mas contém, por
exemplo, um hífen, nunca é atribuído a um dia de PlayerGuess.

## Configurações do administrador

*Administração do site → Plugins → Plugins locais → PlayerGames → Regras → Regras dos jogos*

| Configuração | Padrão | O que faz |
|---|---|---|
| **Máximo de tentativas** | 6 | Tentativas permitidas antes da rodada terminar em derrota e o termo ser revelado. |
| **Comprimento mínimo do termo** | 4 | Menor termo elegível para a atribuição diária. |
| **Comprimento máximo do termo** | 8 | Maior termo elegível para a atribuição diária. |

*Administração do site → Plugins → Plugins locais → PlayerGames → Regras → XP dos jogos*

| Configuração | Padrão | O que faz |
|---|---|---|
| **XP por jogo do Guess** | 25 | XP concedido numa rodada vencida. |

O Guess tem um conceito por dia, então tem uma rodada por dia e paga o XP uma vez, na vitória; não
existe configuração de "jogos por dia" para ele.

## Estado da rodada

A rodada em andamento (tentativas feitas até agora, se está finalizada, se foi vencida) fica
guardada no banco de dados, uma linha por usuário e dia. Ela reseta automaticamente se a rodada
guardada não corresponder mais ao conceito atribuído hoje (um novo dia, ou um conceito
reatribuído). Isso significa:

- Sair da página e voltar — mesmo depois de sair da conta ou de outro navegador — retoma a rodada
  exatamente de onde parou, incluindo uma rodada finalizada: uma vitória ou derrota permanece final
  pelo resto do dia, e o limite de tentativas não pode ser reiniciado entrando de novo.
- Apenas uma **vitória** paga XP (pelo mesmo mecanismo de contagem diária usado pelos demais
  jogos). A derrota é guardada para que a rodada continue perdida, mas não rende nada.
- O termo só é revelado quando a rodada termina.
- As linhas de dias anteriores são apagadas pela tarefa diária de limpeza.
