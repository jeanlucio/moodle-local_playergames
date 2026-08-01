# 📝 Como o PlayerFill Funciona

O PlayerFill é um minijogo diário estilo cruzadinha: resolva todos os termos do dia, onde letras
compartilhadas entre os termos são reveladas automaticamente conforme os outros termos são
resolvidos.

## O fluxo da jogada

- A cruzadinha de cada dia é composta por vários termos (veja "Quantidade de palavras por dia"
  abaixo), exibidos como uma linha por termo, com a **definição** como dica permanente.
- Cada posição de letra na cruzadinha recebe um **número de slot** compartilhado: a mesma letra,
  em qualquer lugar entre os termos do dia, sempre recebe o mesmo número. Uma posição oculta mostra
  seu número de slot em vez de um espaço em branco; uma vez revelada, mostra a letra.
- Uma tentativa é validada como **palavra inteira**, não letra por letra — digite o termo completo
  de uma linha e envie.
- **Acerto** → aquela linha é marcada como resolvida, e todo slot que ela cobre é revelado em
  qualquer outro lugar da cruzadinha. Isso pode resolver imediatamente outra linha ainda pendente
  também, quando todas as suas letras já estiverem cobertas pelas linhas resolvidas até então (o
  efeito cascata) — sem que o jogador precise digitar a resposta daquela linha diretamente.
- **Resolver todos os termos** → o dia termina em vitória e o XP é concedido.
- **Esgotar as tentativas em qualquer termo** → o dia inteiro termina em derrota imediatamente
  (já que vencer exige resolver todos os termos, um termo esgotado já torna isso impossível), e a
  grafia de cada termo não resolvido é revelada.
- A validação da tentativa sempre acontece no servidor contra os termos atribuídos do dia — o
  cliente nunca recebe nem confia em nada sobre a grafia de um termo não resolvido até que sua
  linha seja resolvida ou o dia termine.

## Quantidade de palavras por dia, pipeline de atribuição compartilhado

Toda noite, uma tarefa agendada atribui um número configurável de conceitos ao PlayerFill (padrão
5, ajustável pelo admin entre 4 e 8) a partir dos cartuchos ativos no momento — o mesmo pipeline
usado entre os minijogos diários, estendido para a necessidade do PlayerFill de vários termos de
uma vez em vez de um só. Apenas conceitos cujo termo:

- tenha comprimento normalizado dentro da faixa configurada pelo admin (padrão 3–12 letras), e
- seja uma única palavra alfabética — sem espaços, dígitos ou hífens

são elegíveis, o mesmo filtro que o PlayerGuess aplica ao seu próprio termo diário único. Se
existirem menos conceitos elegíveis do que a quantidade de palavras configurada, o PlayerFill
simplesmente fica sem atribuição naquele dia, em vez de iniciar uma cruzadinha incompleta.

## Configurações do administrador

*Administração do site → Plugins → Plugins locais → PlayerGames → Regras dos jogos*

| Configuração | Padrão | O que faz |
|---|---|---|
| **Palavras por dia do Fill** | 5 | Quantos termos compõem a cruzadinha de cada dia. Limitado entre 4 e 8. |
| **Tentativas máximas por palavra do Fill** | 4 | Tentativas permitidas num único termo antes que o dia inteiro termine em derrota. |
| **Comprimento mínimo de termo para PlayerFill** | 3 | Menor termo elegível para a atribuição diária. |
| **Comprimento máximo de termo para PlayerFill** | 12 | Maior termo elegível para a atribuição diária. |

*Administração do site → Plugins → Plugins locais → PlayerGames → XP e recompensas*

| Configuração | Padrão | O que faz |
|---|---|---|
| **XP por jogo de Completar** | 25 | XP concedido num dia vencido. |
| **Jogos de Completar por dia** | 1 | Quantas vitórias por dia valem XP — atingido o limite, o jogo mostra "já jogou hoje". |

## Estado da rodada e seus limites

A cruzadinha em andamento (quais termos estão resolvidos, quais slots estão revelados, tentativas
usadas por termo) fica guardada na **sessão** do usuário, não numa tabela de banco de dados — a
mesma escolha que o PlayerGuess faz, e pelo mesmo motivo: toda tentativa precisa de validação no
servidor contra um termo que nunca pode ser exposto ao navegador antes da hora. Ela é reconstruída
automaticamente se a cruzadinha guardada não corresponder mais aos termos atribuídos hoje (um novo
dia, ou um conjunto reatribuído).

- Sair da página e voltar dentro da mesma sessão retoma a cruzadinha exatamente de onde parou,
  incluindo um dia finalizado — uma vitória ou derrota permanece final pelo resto do dia naquela
  sessão.
- Apenas uma **vitória** é registrada no servidor (pelo mesmo mecanismo de contagem diária usado
  pelos demais jogos); uma derrota não é gravada em lugar nenhum fora da sessão.
- **Limitação conhecida:** como uma derrota não deixa rastro no servidor, sair da conta ou
  continuar em uma sessão de navegador diferente dá uma cruzadinha nova para os mesmos termos do
  dia — a nova sessão não tem memória da derrota anterior. Uma vitória não é afetada: ela já foi
  registrada, então a tela de "já jogou hoje" continua valendo. Isso espelha a mesma escolha aceita
  pelo PlayerGuess.

## Adaptado do mod_playercross

O algoritmo de letra-para-slot do PlayerFill foi portado do construtor de cruzadinhas da atividade
standalone `mod_playercross`. O próprio `mod_playercross` evoluiu para uma mecânica mais rica em
cima dele — uma "frase-mistério" cifrada à parte, resolvida através de palavras-pista, com um bônus
por adivinhá-la diretamente e nota parcial por tentativa — nada disso foi trazido para cá: o
PlayerGames concede uma quantidade fixa de XP por vitória independente de como ela foi alcançada,
então nota parcial não tem lugar neste hub, e um conjunto de termos-pares que se revelam entre si
já é uma cruzadinha completa e autossuficiente, sem precisar de uma frase separada por cima.
