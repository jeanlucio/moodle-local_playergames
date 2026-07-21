# 📘 XP de Aprendizado

O XP de Aprendizado é um pool de XP separado e opcional que espelha o **XP por curso ganho no
`block_playerhud`** de um estudante para dentro do PlayerGames, puramente para visibilidade entre
cursos — nunca é somado ao XP de temporada e nunca afeta níveis, desbloqueios de avatar ou o
ranking de temporada.

## De onde vem

Quando o `block_playerhud` está instalado e um estudante ganha ou perde XP de curso, o
PlayerGames escuta essa mudança e espelha a diferença no seu próprio total **com janela** de XP
de aprendizado. Nada precisa ser configurado do lado do `block_playerhud` além de tê-lo instalado
— a ponte é opcional e fica inerte se o `block_playerhud` não estiver presente.

## A janela

O XP de Aprendizado não é um total vitalício: só conta o XP ganho dentro de uma **janela
móvel** (padrão de 12 meses, configurável em meses; `0` significa ilimitado/vitalício). Os dados
ficam guardados em blocos mensais, então a janela envelhece corretamente mês a mês, e uma tarefa
agendada noturna recalcula o total com janela de cada usuário para corrigir qualquer desvio e
descartar blocos que já saíram da janela.

## Visibilidade

O XP de Aprendizado só é exibido quando **ambas** as condições são verdadeiras:
- o admin ativou (*Administração do site → Plugins → Plugins locais → PlayerGames → XP de
  Aprendizado*), e
- a configuração de participantes do site permite estudantes (`students` ou `both`).

Diferente da maioria das outras funcionalidades voltadas ao estudante, a visibilidade **não**
depende de o visualizador estar marcado como staff — um professor que também é um estudante
genuíno ganhando XP do PlayerHUD em outro curso ainda vê o próprio XP de aprendizado.

## Seu próprio ranking

O XP de Aprendizado tem seu **próprio ranking opt-in separado** — uma única lista top-50 (não
dividida por staff/estudante como o ranking de temporada), controlada por seu próprio toggle de
admin. Jogadores com XP com janela igual a zero são excluídos completamente em vez de ocupar uma
posição, e cada jogador tem um toggle de opt-in independente do usado na visibilidade do ranking
de temporada. A busca de posição para jogadores fora do top 50 segue a mesma regra de desempate
do ranking de temporada (maior XP primeiro, depois quem chegou lá primeiro).

## Onde aparece

Tanto a página do Player Hub quanto o widget de sidebar do [Bloco PlayerGames](#ecosystem) leem
do mesmo gerenciador e seguem as mesmas regras de visibilidade e ranking, então as duas telas
estão sempre consistentes entre si.
