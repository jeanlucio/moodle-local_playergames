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
| | Temporadas | O período em que os estudantes acumulam XP e disputam o ranking. Crie, ative e encerre as temporadas. | Administrador do site |
| | Jogos da temporada | Quais jogos rodam na temporada e de onde vêm as questões de cada um. | Administrador do site |
| **Regras** | Participação e temporadas | Quem joga (estudantes, corpo docente ou ambos), se as temporadas se renovam sozinhas e quanto duram. | Administrador do site |
| | XP dos jogos | Quanto XP cada jogo dá e quantas vezes por dia ele rende XP. | Administrador do site |
| | Regras dos jogos | Tempo por questão, tentativas e as demais regras de cada jogo. | Administrador do site |
| | Níveis e títulos | Quanto XP cada nível exige e o título que ele dá. | Administrador do site |
| | Avatares | A partir de que nível cada grupo de avatares é liberado. | Administrador do site |
| | Sequência e congelamento | Como a sequência diária funciona e como se ganha um congelamento. | Administrador do site |
| | XP de aprendizado | O XP que os cursos geram pelo PlayerHUD, mostrado à parte do XP da temporada. | Administrador do site |
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

## A mesma ordem na administração do Moodle

As páginas do plugin em **Administração do site → Plugins → Plugins locais → PlayerGames** seguem os
mesmos grupos: **Começar** (Cartuchos, Temporadas, Jogos da temporada), **Regras** (Participação e
temporadas, XP dos jogos, Regras dos jogos, Níveis e títulos, Avatares, Sequência e congelamento, XP de
aprendizado) e **Acompanhamento** (Engajômetro). A aba Administração é um atalho para elas, não um
substituto.

Jogos da temporada abre a temporada ativa, ou a próxima se nenhuma estiver ativa. Sem nenhuma temporada,
leva à página de temporadas.

## Primeira configuração

1. Crie um cartucho (**Cartuchos**): os jogos precisam de algo para perguntar.
2. Crie uma temporada e ative-a (**Temporadas**).
3. Escolha os jogos que a temporada roda e as fontes deles (**Jogos da temporada**).
4. Revise as regras (**Regras**): os padrões funcionam, mas o XP, os níveis e quem joga são seus para definir.
