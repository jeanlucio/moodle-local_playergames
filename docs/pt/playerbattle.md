# ⚔️ Como o PlayerBattle Funciona

O PlayerBattle é o minijogo diário de combinar peças (match-3). O jogador enfrenta um oponente num
tabuleiro 8×8: alinhar peças fere o oponente, e o **Orbe** traz uma questão — acertar causa um golpe
crítico. As questões vêm das mesmas fontes do PlayerQuiz.

## O tabuleiro

Troque duas peças vizinhas para alinhar três ou mais do mesmo tipo. As peças alinhadas são destruídas,
novas caem no lugar, e qualquer combinação que elas formem também vale (uma cascata). Uma troca que não
forma combinação volta ao lugar e não gasta o turno. O jogador joga primeiro, depois o oponente, em turnos.

| Peça | Efeito |
|---|---|
| ⭐ **Estrela** | +0,1 no multiplicador de dano de quem a combinou. O multiplicador aumenta o dano das Espadas e o golpe crítico, e é gasto pelo golpe crítico ou perdido ao errar uma questão. |
| 📖 **Grimório** | Enche um medidor de veneno. Cheio, o outro lado fica envenenado por três turnos: perde a unidade de dano de quem o envenenou no início de cada turno seu, e o escudo não impede. |
| ❓ **Orbe** | Enche a barra de mana (20 por peça, 100 é cheia). Quando ela enche, o jogador encara uma questão. |
| ⚔️ **Espada** | Dano direto: uma unidade de dano numa combinação de três, ×1,5 com quatro, ×2 com cinco. |
| 🛡️ **Escudo** | Enche um medidor de escudo. Cheio, cancela o próximo golpe recebido (de Espada, crítico ou de uma resposta errada). O veneno o atravessa. |
| 🧪 **Poção** | Cura um quarto da unidade de dano por peça, até o HP máximo. |

Os dois lados começam com 200 de HP. Uma combinação de três Espadas causa 10 de dano quando é do jogador e
8 quando é do oponente. Esses números são fixos: vieram de batalhas simuladas, nas quais quem acerta todas
as questões vence quase sempre, quem acerta duas em cada três vence cerca de três vezes em quatro, e quem
chuta entre cinco opções vence cerca de uma vez em cinco. Uma batalha dura uns 18 a 29 turnos e faz de 5 a
9 questões.

## As questões

- Quando a barra de mana enche, o tabuleiro para e aparece uma questão; a batalha continua depois da resposta.
- **Acertar** é um golpe crítico no oponente: três vezes a unidade de dano, vezes o multiplicador.
- **Errar** custa ao jogador um golpe simples do oponente, e o multiplicador.
- O oponente não tem questões: quando a mana dele enche, ele dá um golpe crítico sozinho.
- Dez questões são sorteadas para cada batalha e feitas em ordem embaralhada. Se acabarem, a ordem é
  sorteada de novo, sem começar pela questão que acabou de ser feita.
- O texto de cartucho é mostrado como texto simples; o do Banco de Questões passa pelos filtros do Moodle.
  O feedback da questão, quando ela tem um, aparece depois da resposta.

## XP e jogadas por dia

O PlayerBattle segue a mesma regra diária dos outros jogos: quanto XP uma jogada dá e quantas jogadas por
dia contam vêm das configurações de XP dos jogos com que a temporada foi criada.

- **Uma vitória** gasta uma jogada do dia e dá o XP do jogo na temporada. Além da cota do dia, a vitória
  ainda é salva, mas não dá XP.
- **Uma derrota** é salva, mas não dá XP e **não gasta a jogada**: o jogador pode tentar de novo na hora.
- O dia guarda o **melhor resultado**: uma vitória vale mais que uma derrota, e depois vale a que causou
  mais dano.
- A primeira vitória completa a missão **Primeira Vitória**.
- As questões mostradas contam como vistas no dia, também numa derrota, então a próxima tentativa traz
  questões novas. Quando todas já foram vistas, elas se repetem em vez de travar o jogador.

Um resultado é aceito uma vez para cada batalha que a página abriu, então um resultado enviado duas vezes
não rende nada.

O tabuleiro é jogado no navegador e o servidor não consegue reproduzi-lo, então confere o que pode. As
questões são julgadas no servidor: a página nunca recebe qual alternativa é a certa, e as contagens de
questões respondidas e certas são as do servidor. Uma vitória só é registrada como vitória se a batalha
durou pelo menos 15 segundos e respondeu pelo menos 2 questões; senão é registrada como derrota. Isso
barra um resultado enviado sem jogar. Não barra quem joga até o fim com um script que responde às
questões e espera.

## Conteúdo e configurações

O PlayerBattle pergunta **cartuchos do tipo quiz** e, opcionalmente, uma categoria do **Banco de Questões do
Moodle** mantida para reuso (questões de múltipla escolha; ver [Como o PlayerQuiz Funciona](#playerquiz)), conforme escolhido para a temporada no card *Jogos da temporada* da aba
[Administração](#administration).

| Onde | Configuração |
|---|---|
| *Jogos da temporada* | Se o PlayerBattle roda na temporada e de onde vêm as questões dele. |
| *Regras → XP dos jogos* | XP por jogo do PlayerBattle e quantas jogadas por dia rendem XP. |

## Jogando

- **Mouse ou toque:** clique numa peça e depois numa vizinha, ou arraste uma peça na direção de uma vizinha.
- **Teclado:** **Tab** entra no tabuleiro, as setas movem entre as peças, **Enter** ou **Espaço** seleciona
  uma, e uma seta troca a peça com a vizinha naquela direção. **Esc** desfaz a seleção.
- **Leitores de tela:** cada casa do tabuleiro é um botão com o nome da linha, da coluna e da peça, e a
  selecionada fica marcada como pressionada; o que acontece na batalha é anunciado.
- A questão e o fim da batalha são diálogos: enquanto um está aberto, o teclado fica dentro dele. As
  respostas também podem ser escolhidas com as teclas 1 a 5 ou A a E.
- As animações são puladas quando o navegador pede movimento reduzido.
- O **som** vem desligado até o jogador ligá-lo no botão *Som*; a escolha fica guardada naquele navegador.

## Privacidade

O resultado de cada dia fica guardado com o jogador: se foi vitória, o dano causado, as questões acertadas e
o total, o XP, a duração e o horário. Ele é exportado e apagado junto com os demais dados do jogador, e
removido com a temporada pela tarefa de retenção.
