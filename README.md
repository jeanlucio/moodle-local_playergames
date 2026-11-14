# Moodle Local PlayerGames

![Moodle](https://img.shields.io/badge/Moodle-4.5%2B-orange?style=flat&logo=moodle&logoColor=white)
![License](https://img.shields.io/badge/License-GPLv3-blue?style=flat)
![Status](https://img.shields.io/badge/Status-Alpha-red?style=flat)
[![Latest Release](https://img.shields.io/github/v/release/jeanlucio/moodle-local_playergames?style=flat)](https://github.com/jeanlucio/moodle-local_playergames/releases)
[![PlayerGames Ecosystem](https://img.shields.io/badge/PlayerGames-Ecosystem-6f42c1?style=flat&logo=gamepad&logoColor=white)](https://jeanlucio.github.io/playergames/)
![Core Component](https://img.shields.io/badge/Role-Central_Hub-198754?style=flat)
[![Author](https://img.shields.io/badge/by-Jean_Lucio-6f42c1?style=flat)](https://github.com/jeanlucio/)

[![Moodle Plugin CI](https://github.com/jeanlucio/moodle-local_playergames/actions/workflows/ci.yml/badge.svg)](https://github.com/jeanlucio/moodle-local_playergames/actions/workflows/ci.yml)
[![Last Commit](https://img.shields.io/github/last-commit/jeanlucio/moodle-local_playergames?style=flat)](https://github.com/jeanlucio/moodle-local_playergames/commits)
[![Open Issues](https://img.shields.io/github/issues/jeanlucio/moodle-local_playergames?style=flat)](https://github.com/jeanlucio/moodle-local_playergames/issues)

> ⚠️ **This plugin is under active development.** It is not yet published on the Moodle Plugin Directory. Some features described in the full documentation are planned and not yet implemented.

[English](#english) | [Português](#português)

---

## English

**PlayerGames** is the central hub of the PlayerGames gamification ecosystem for Moodle. It
provides a site-wide **Player Hub** (XP, levels, seasons, missions, achievements, avatars,
streak), an **Ecosystem Dashboard**, daily concept-reinforcement mini-games powered by content
cartridges, and a companion sidebar block (`block_playergames`).

📚 **[Full documentation](https://jeanlucio.github.io/moodle-local_playergames/)** — features,
the Administration tab, the AI provider chain, the cartridge format, how the daily games (PlayerQuiz,
PlayerGuess, PlayerFill and PlayerBattle) work, avatars, learning XP, ranking behavior, the educational
purpose, the PlayerGames ecosystem, the full test suite, and third-party service disclosure.

### 🔎 Third-party Service Disclosure

AI-assisted cartridge generation is **optional** and disabled until a provider is available.
PlayerGames resolves a tiered chain of personal/site keys through the companion
[local_aihub](https://github.com/jeanlucio/moodle-local_aihub) plugin before falling back to
Moodle's own `core_ai` subsystem, and never sends student data — only content-generation prompts
when a feature is explicitly used.

* **Cost:** None required by PlayerGames itself. If `local_aihub` is installed, any cost is
  whatever the underlying provider charges through that plugin's own BYOK keys; without it,
  PlayerGames falls back to Moodle's `core_ai`, which may be free if the site admin has
  configured a no-cost institutional provider.
* **API keys:** Not configured in PlayerGames. Obtain and configure a personal or site key
  inside `local_aihub` (see its own documentation), or ask the site administrator to configure
  a `core_ai` provider instead.
* **Demo credentials:** Not applicable — no credentials are required to install or use
  PlayerGames; AI-assisted cartridge generation is entirely opt-in.

Full disclosure:
[Third-party Service Disclosure](https://jeanlucio.github.io/moodle-local_playergames/#security).

### 📦 Requirements

| Component | Version |
|-----------|---------|
| Moodle    | 4.5 – 5.3 |
| PHP       | 8.1+    |

### 🛠️ Installation & Configuration

> ⚠️ This plugin is not yet published on the Moodle Plugin Directory. Install manually from this repository.

1. Download the `.zip` file or clone this repository.
2. Extract the folder into your Moodle `local/` directory.
3. Rename the folder to `playergames` (if necessary).
   Final path:
   `your-moodle/local/playergames/`
4. Visit **Site administration > Notifications** to complete installation.
5. Open the **Administration** tab of PlayerGames (or **Site administration > Plugins > Local
   plugins > PlayerGames**) and follow its cards in order: create a cartridge, create and activate
   the first season, and choose the games it runs. The XP, level, avatar and game rules have working
   defaults you can review under **Rules**.

### 🆘 Support

Found a bug or have a question? Open an issue on the
[issue tracker](https://github.com/jeanlucio/moodle-local_playergames/issues).

### 🎨 Credits

* **Sounds and music:** the effects and the background music are public domain (CC0), from Kenney and RandomMind on OpenGameArt. The sources are listed in `pix/readme_moodle.txt`.
* **Images:** the board pieces were generated with Google Gemini, created for this ecosystem from original prompts. The art direction, the prompts and the review of every image are the author's, who is responsible for the content.

### 📄 License

This project is licensed under the **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

### 👤 Maintainer

Maintained by [Jean Lúcio](https://github.com/jeanlucio).

[⬆️ Back to top](#english)

---

## Português

> ⚠️ **Este plugin está em desenvolvimento ativo.** Ainda não foi publicado no Diretório de Plugins do Moodle. Algumas funcionalidades descritas na documentação completa são planejadas e ainda não estão implementadas.

O **PlayerGames** é o hub central do ecossistema de gamificação PlayerGames para o Moodle. Ele
fornece um **Player Hub** site-wide (XP, níveis, temporadas, missões, conquistas, avatares,
streak), um **Dashboard do Ecossistema**, minijogos diários de reforço de conceitos alimentados
por cartuchos de conteúdo, e um bloco companheiro de sidebar (`block_playergames`).

📚 **[Documentação completa](https://jeanlucio.github.io/moodle-local_playergames/pt.html)** —
funcionalidades, a aba Administração, a cadeia de provedores de IA, o formato do cartucho, como os
jogos diários (PlayerQuiz, PlayerGuess, PlayerFill e PlayerBattle) funcionam, avatares, XP de
aprendizado, como o ranking funciona, a finalidade educacional, o ecossistema PlayerGames, a suíte
completa de testes, e a divulgação de serviço de terceiros.

### 🔎 Divulgação de Serviço de Terceiros

A geração de cartuchos assistida por IA é **opcional** e fica desativada até que um provedor
esteja disponível. O PlayerGames percorre uma cadeia de níveis de chaves pessoais/de site pelo
plugin companheiro [local_aihub](https://github.com/jeanlucio/moodle-local_aihub) antes de
recorrer ao subsistema `core_ai` do próprio Moodle, e nunca envia dados de estudante — apenas os
prompts de geração de conteúdo quando um recurso é usado explicitamente.

* **Custo:** Nenhum é exigido pelo próprio PlayerGames. Se o `local_aihub` estiver instalado,
  qualquer custo é o que o provedor cobrar através das chaves BYOK desse plugin; sem ele, o
  PlayerGames recorre ao `core_ai` do Moodle, que pode ser gratuito se o administrador do site
  tiver configurado um provedor institucional sem custo.
* **Chaves de API:** Não são configuradas no PlayerGames. Obtenha e configure uma chave pessoal
  ou do site dentro do `local_aihub` (veja a documentação própria dele), ou peça ao
  administrador do site para configurar um provedor `core_ai`.
* **Credenciais de demonstração:** Não aplicável — nenhuma credencial é exigida para instalar ou
  usar o PlayerGames; a geração de cartuchos assistida por IA é totalmente opcional.

Divulgação completa:
[Divulgação de Serviço de Terceiros](https://jeanlucio.github.io/moodle-local_playergames/pt.html#security).

### 📦 Requisitos

| Componente | Versão |
|------------|--------|
| Moodle     | 4.5 – 5.3 |
| PHP        | 8.1+   |

### 🛠️ Instalação e Configuração

> ⚠️ Este plugin ainda não está publicado no Diretório de Plugins do Moodle. Instale manualmente a partir deste repositório.

1. Baixe o arquivo `.zip` ou clone este repositório.
2. Extraia na pasta `local/` do seu Moodle.
3. Renomeie para `playergames` (se necessário).
   Caminho final:
   `seu-moodle/local/playergames/`
4. Acesse **Administração do site > Notificações** para concluir a instalação.
5. Abra a aba **Administração** do PlayerGames (ou **Administração do site > Plugins > Plugins
   locais > PlayerGames**) e siga os cards em ordem: crie um cartucho, crie e ative a primeira
   temporada e escolha os jogos que ela roda. As regras de XP, níveis, avatares e jogos têm padrões
   que funcionam, e você pode revisá-las em **Regras**.

### 🆘 Suporte

Encontrou um bug ou tem alguma dúvida? Abra uma issue no
[rastreador de issues](https://github.com/jeanlucio/moodle-local_playergames/issues).

### 🎨 Créditos

* **Sons e música:** os efeitos e a música de fundo são de domínio público (CC0), de Kenney e RandomMind no OpenGameArt. As fontes estão em `pix/readme_moodle.txt`.
* **Imagens:** os oponentes, cenários e peças do tabuleiro foram gerados com o Google Gemini, criados para este ecossistema a partir de prompts originais. A direção de arte, os prompts e a revisão de todas as imagens são do autor, que responde pelo conteúdo.

### 📄 Licença

Este projeto é licenciado sob a **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

### 👤 Mantenedor

Mantido por [Jean Lúcio](https://github.com/jeanlucio).

[⬆️ Voltar ao topo](#português)
