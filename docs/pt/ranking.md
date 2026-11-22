# 🏆 Como o Ranking Funciona

O ranking de temporada é opt-in, dividido por grupo de participante, e mostra a um jogador sua
própria posição mesmo fora do top 50 visível.

## Opt-in e a lista top-50

Os jogadores escolhem se querem aparecer no ranking a partir do próprio perfil. A lista visível é
o **top 50** por XP de temporada, ordenada por `XP decrescente → o momento em que atingiu esse XP
crescente → id do usuário crescente` — um desempate totalmente determinístico, então dois
jogadores com o mesmo XP são ordenados por quem chegou lá primeiro.

## Estudantes e professores e gestores

Os rankings são divididos em dois grupos: **estudantes** e **professores e gestores**. Professores e gestores são os administradores do
site e todos que podem editar pelo menos um curso, como gerentes e professores com permissão de edição. Os
demais contam como estudantes, inclusive o professor sem permissão de edição. Se um visualizador vê um
grupo ou os dois depende do próprio papel e da configuração de participantes do site:

- Um estudante ou professor comum vê apenas o ranking do próprio grupo.
- Um admin, ou qualquer professor ou gestor quando o site está configurado para permitir **ambos**
  os grupos, vê **ambas** as abas — Estudantes e Professores e gestores.

## Sua própria posição, mesmo fora do top 50

Um jogador que optou por entrar no ranking mas não está no top 50 visível ainda vê sua **posição
exata** — calculada com uma única consulta de contagem indexada usando a mesma regra de
ordenação da lista visível, em vez de varrer o ranking inteiro.

## Quem permanece na lista

Sair da gamificação (Preferências → Gamificação) tira o jogador dos dois rankings na hora, e ao voltar
ele começa oculto: escolhe de novo se quer aparecer. Um jogador cujo papel perdeu a capacidade
`local/playergames:viewhub`, ou que uma mudança na configuração de participantes deixou de permitir,
também sai da lista, em até um minuto (as listas ficam em cache por 60 segundos).

## Como o grupo de professores e gestores é mantido atualizado

Descobrir quem é professor ou gestor exige várias consultas para cada professor do site, então uma tarefa
agendada, **Atualizar o conjunto de professores e gestores** (`refresh_staff_ids`), faz isso **a cada 10
minutos** e o hub apenas lê o resultado. Isso tem duas consequências para quem administra:

- Uma mudança nos papéis de alguém pode levar cerca de 10 minutos para movê-lo entre os grupos
  Estudantes e Professores e gestores. O que a pessoa pode fazer nunca é decidido por essa cópia: as páginas e os
  serviços conferem o papel na hora.
- O site precisa do **cron rodando**. Se ele não estiver, o plugin continua funcionando: depois de
  uma hora sem atualização, o hub refaz o conjunto sozinho, ao custo de uma página mais lenta para
  quem a abrir primeiro.

## Escopo

Esta página descreve o ranking de **temporada** (ligado ao XP de temporada e reiniciado quando
uma temporada fecha). O [XP dos cursos](#learning-xp) tem seu próprio ranking separado,
único (não dividido entre estudantes e professores e gestores) e com seu próprio opt-in — veja aquela seção para
detalhes.
