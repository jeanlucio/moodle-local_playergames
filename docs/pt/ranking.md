# 🏆 Como o Ranking Funciona

O ranking de temporada é opt-in, dividido por grupo de participante, e mostra a um jogador sua
própria posição mesmo fora do top 50 visível.

## Opt-in e a lista top-50

Os jogadores escolhem se querem aparecer no ranking a partir do próprio perfil. A lista visível é
o **top 50** por XP de temporada, ordenada por `XP decrescente → o momento em que atingiu esse XP
crescente → id do usuário crescente` — um desempate totalmente determinístico, então dois
jogadores com o mesmo XP são ordenados por quem chegou lá primeiro.

## Divisão staff / estudante

Os rankings são divididos em dois grupos: **estudantes** e **staff** (qualquer um com capacidade
de edição de curso em pelo menos um curso, ou a flag de admin do site). Se um visualizador vê um
grupo ou os dois depende do próprio papel e da configuração de participantes do site:

- Um estudante ou professor comum vê apenas o ranking do próprio grupo.
- Um admin, ou qualquer membro do staff quando o site está configurado para permitir **ambos**
  os grupos, vê **ambas** as abas — Estudantes e Staff.

## Sua própria posição, mesmo fora do top 50

Um jogador que optou por entrar no ranking mas não está no top 50 visível ainda vê sua **posição
exata** — calculada com uma única consulta de contagem indexada usando a mesma regra de
ordenação da lista visível, em vez de varrer o ranking inteiro.

## Escopo

Esta página descreve o ranking de **temporada** (ligado ao XP de temporada e reiniciado quando
uma temporada fecha). O [XP de Aprendizado](#learning-xp) tem seu próprio ranking separado,
único (não dividido por staff/estudante) e com seu próprio opt-in — veja aquela seção para
detalhes.
