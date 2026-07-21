# 🦊 Avatares

O PlayerGames tem sua própria coleção de avatares emoji, desbloqueados permanentemente pelo maior
nível que o jogador já alcançou — sem loja, sem upload de arquivo, apenas uma recompensa por
faixa de nível.

## Catálogo e faixas

O catálogo padrão tem **17 avatares emoji** divididos em **4 faixas** (5 na faixa 1, 4 em cada
uma das faixas 2 a 4): 🤖 👾 🦊 🐱 🐶 (faixa 1), 🕵️ 🤺 🧚 🧜 (faixa 2), 🧝 🧙 🦸 🥷 (faixa 3), e
🧛 🦹 🐉 🦁 (faixa 4).

## Regra de desbloqueio

Os desbloqueios são baseados no **maior nível que o jogador já alcançou, para sempre** — não no
nível atual, e não restrito à temporada corrente. Toda vez que um jogador ganha XP suficiente
para subir de nível, o PlayerGames registra o novo nível apenas se ele superar o melhor nível já
guardado para aquele jogador; fechar uma temporada, que zera o XP de temporada, nunca tira um
avatar já desbloqueado.

## Limiares por faixa

| Faixa | Nível mínimo padrão |
|---|---|
| 1 | 1 |
| 2 | 5 |
| 3 | 10 |
| 4 | 20 |

Admins podem alterar o nível mínimo de cada faixa independentemente
(*Administração do site → Plugins → Plugins locais → PlayerGames → Avatares*).

## Equipar e exibir

Um jogador pode equipar qualquer avatar já desbloqueado (ou desequipar, voltando à foto de perfil
padrão do Moodle) num seletor agrupado por faixa — avatares bloqueados aparecem em tons de cinza
junto com o nível exigido. O avatar equipado substitui a foto de perfil padrão no cartão de
perfil do Player Hub e no widget do [Bloco PlayerGames](#ecosystem), e permanece sincronizado
entre os dois, já que ambos leem e escrevem através do mesmo gerenciador de avatares.
