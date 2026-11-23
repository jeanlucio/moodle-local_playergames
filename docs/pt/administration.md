# 🧭 Administração

Tudo o que o administrador configura fica reunido na aba **Administração** do PlayerGames, em cards, na
ordem em que o trabalho costuma ser feito. Cada card diz o que a função faz e, quando há, em que ponto
ela está: qual temporada está ativa, quantos cartuchos estão ligados, e assim por diante. O card é um
link para a página da função.

A aba aparece para quem pode abrir pelo menos um card, e cada card só aparece se o usuário puder abrir
o destino dele.

## Os cards

| Grupo | Card | Para que serve | Exige |
|---|---|---|---|
| **Começar** | Cartuchos | Os pacotes de questões e conceitos de onde os jogos tiram o conteúdo. Importe um arquivo, gere com IA ou crie à mão. | Gerenciar cartuchos |
| | Temporadas | O período em que os estudantes acumulam XP e disputam o ranking. Crie, edite, ative, encerre e exclua as temporadas. | Administrador do site |
| | Jogos da temporada | Quais jogos rodam na temporada e de quais cartuchos vêm as questões ou palavras de cada um (o PlayerQuiz e o PlayerBattle usam cartuchos de questões; o PlayerGuess e o PlayerFill, cartuchos de conceitos). | Administrador do site |
| **Regras** | Participação e temporadas | Quem joga (só estudantes, só professores e gestores, ou todos), se as temporadas se renovam sozinhas e quanto duram. | Administrador do site |
| | XP dos jogos | Quanto XP cada jogo dá e quantas vezes por dia ele rende XP. | Administrador do site |
| | Regras dos jogos | Tempo por questão, tentativas e as demais regras de cada jogo. | Administrador do site |
| | Níveis e títulos | Quanto XP cada nível exige e o título que ele dá. Os avatares se liberam ao longo desta escada. | Administrador do site |
| | Sequência e congelamento | Como a sequência diária funciona e como se ganha um congelamento. | Administrador do site |
| | Missões | As metas que dão XP extra ou congelamento: metas e prêmios, e novas missões dos mesmos tipos. | Administrador do site |
| | XP dos cursos | O XP que os cursos geram pelo PlayerHUD, mostrado à parte do XP da temporada. | Administrador do site |
| **Dados** | Zerar dados | Apaga as temporadas e tudo o que os jogadores conquistaram, para recomeçar do zero. Os cartuchos e as configurações ficam. | Administrador do site |
| **Ajuda e integrações** | Chaves de IA | Chaves usadas para gerar questões com IA. Só aparece com o plugin Central de IA instalado. | Administrador do site |
| | Documentação | Este guia, em português e inglês. | Quem vê a aba |

O Engajômetro e o Ecossistema de Plugins não são cards: já têm aba própria.

## Quem vê o quê

* **Administradores do site** veem todos os cards.
* **Gerentes** (o papel *Gerente* do Moodle) veem **Cartuchos** e **Documentação**: a permissão de
  gerenciar cartuchos é dada aos gerentes por padrão, enquanto temporadas, níveis e regras exigem a
  permissão de mudar a configuração do site, que só os administradores do site têm.
* **Os demais** não têm a aba Administração.

Quando não há temporada ativa, o Player Hub avisa o administrador e oferece um botão para a página de
temporadas.

### Editar e excluir uma temporada

Na página de temporadas cada temporada tem **Editar** e, exceto a ativa, **Excluir**.

* **Editar** muda o nome e as datas. Uma temporada encerrada mantém as datas, que são histórico; só o nome
  muda. O status nunca muda aqui — use **Ativar** e **Encerrar temporada** para isso.
* **Excluir** pede confirmação e remove a temporada com as configurações dos jogos e o XP, as missões e os
  resultados dos jogadores dela. As pontuações diárias desses dias também saem, a menos que outra temporada
  cubra os mesmos dias. A temporada ativa não pode ser excluída: encerre-a primeiro. Não dá para desfazer.
* **Encerrar temporada** também pede confirmação, porque uma temporada encerrada não pode ser reaberta.
  Encerrada antes do término previsto, a temporada termina hoje: a data de término passa a ser a de hoje, e os
  dias que ela não usou ficam livres para a próxima temporada.

Temporadas nunca compartilham um dia. Criar ou editar uma temporada cujas datas cruzam outra é recusado, com o
nome e as datas da outra, porque a tarefa de retenção e o **Excluir** removem as pontuações de uma temporada
pelas datas dela. O formulário sugere uma data de início depois da última temporada quando ela ainda está em
curso: depois de encerrar uma temporada hoje, a próxima pode começar amanhã.

### Zerar todos os dados

Excluir as temporadas e os cartuchos um a um deixa o que não pertence a nenhuma temporada: sequências,
congelamentos, conquistas, progresso dos avatares, o histórico de atividade e o XP dos cursos espelhado do
PlayerHUD. **Zerar dados** apaga tudo isso, de todos os usuários, para recomeçar do zero, depois de um
piloto, por exemplo.

* **Apagado:** todas as temporadas, inclusive a ativa, com a configuração dos jogos; os resultados das
  temporadas e o hall da fama; as pontuações diárias, batalhas e rodadas; os perfis da temporada e o
  progresso das missões; as sequências, congelamentos, conquistas e progresso dos avatares; o histórico de
  atividade e de congelamentos; o XP dos cursos espelhado do PlayerHUD (o XP do próprio PlayerHUD não é
  tocado).
* **Mantido:** os cartuchos com suas questões e conceitos, os níveis e títulos, o catálogo de avatares, as
  próprias missões e conquistas, todas as configurações e a escolha de cada usuário de participar.

A página mostra quantos registros há em cada grupo, e nada é apagado até o administrador digitar a palavra de
confirmação (*ZERAR*; *RESET* em inglês). Não dá para desfazer. O log do site registra quem fez, quando e
quantos registros saíram (evento *Dados do PlayerGames zerados*).

## A mesma ordem na administração do Moodle

As páginas do plugin em **Administração do site → Plugins → Plugins locais → PlayerGames** seguem os
mesmos grupos: **Começar** (Cartuchos, Temporadas, Jogos da temporada), **Regras** (Participação e
temporadas, XP dos jogos, Regras dos jogos, Níveis e títulos, Sequência e congelamento, Missões,
XP dos cursos), **Acompanhamento** (Engajômetro) e **Dados** (Zerar dados). A aba Administração é um atalho para elas, não um
substituto.

## Missões

Uma missão pede uma de cinco coisas: jogos no mesmo dia, uma sequência de dias, XP na temporada, vitórias no
PlayerBattle ou check-ins seguidos. O site começa com uma de cada, e o administrador pode mudar a meta e os prêmios,
dar um nome, desativar ou adicionar outras dos mesmos tipos (uma sequência de 30 dias que vale mais XP, por exemplo).
Cada missão vale uma vez por temporada, menos as diárias, que recomeçam todo dia.

Depois que alguém conclui uma missão na temporada ativa, ela não pode ser desativada e a meta e os prêmios ficam
como estão até o fim da temporada, para que todos joguem pelas mesmas regras; só o nome pode mudar. Uma missão que
alguém já começou pode ser desativada, mas não excluída, porque as temporadas contam as missões que seus jogadores
concluíram.

## XP máximo da temporada

A página de temporadas mostra, para cada temporada, o máximo de XP que um jogador pode ganhar nela: todos os jogos
vencidos em todos os dias, todos os check-ins até o limite e todas as missões que a temporada permite cumprir. Os
jogos e o check-in seguem as regras que a temporada guardou ao ser criada; as missões são as ativas agora. O XP dos
cursos fica à parte e não entra na conta.

Jogos da temporada abre a temporada ativa, ou a próxima se nenhuma estiver ativa. Sem nenhuma temporada,
leva à página de temporadas.

Com todos os cartuchos marcados, o jogo usa também os criados depois. Sem nenhum marcado, o jogo fica desligado na
temporada: o interruptor acompanha as caixas, e ligá-lo de novo marca todos.

No PlayerGuess e no PlayerFill, cada cartucho mostra quantas das suas palavras servem para o jogo, e a página avisa
quando os cartuchos escolhidos não bastam. Na central, o cartão do jogo diz o motivo de não ter o que jogar: sem
cartucho ativo, sem palavras que atendam aos requisitos ou sem questões nos cartuchos escolhidos, ou palavras do dia
ainda não sorteadas. Excluir o único cartucho que um jogo usa desliga o jogo, e a página avisa.

## Primeira configuração

1. Crie um cartucho (**Cartuchos**): os jogos precisam de algo para perguntar.
2. Crie uma temporada e ative-a (**Temporadas**).
3. Escolha os jogos que a temporada roda e os cartuchos deles (**Jogos da temporada**).
4. Revise as regras (**Regras**): os padrões funcionam, mas o XP, os níveis e quem joga são seus para definir.
