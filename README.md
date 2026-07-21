# Moodle Local PlayerGames

[![Moodle Plugin CI](https://github.com/jeanlucio/moodle-local_playergames/actions/workflows/ci.yml/badge.svg)](https://github.com/jeanlucio/moodle-local_playergames/actions/workflows/ci.yml)
![Moodle](https://img.shields.io/badge/Moodle-4.5%2B-orange?style=flat-square&logo=moodle&logoColor=white)
![License](https://img.shields.io/badge/License-GPLv3-blue?style=flat-square)
![Status](https://img.shields.io/badge/Status-In%20Development-yellow?style=flat-square)
[![PlayerGames Ecosystem](https://img.shields.io/badge/PlayerGames-Ecosystem-6f42c1?style=flat-square&logo=gamepad&logoColor=white)](https://moodle.org/plugins/browse.php?list=contributor&id=3970322)
![Core Component](https://img.shields.io/badge/Role-Central%20Hub-198754?style=flat-square)

> ⚠️ **This plugin is under active development.** It is not yet published on the Moodle Plugin Directory. Some features described in the full documentation are planned and not yet implemented.

[English](#english) | [Português](#português)

---

## English

**PlayerGames** is the central hub of the PlayerGames gamification ecosystem for Moodle. It
provides a site-wide **Player Hub** (XP, levels, seasons, missions, achievements, avatars,
streak), an **Ecosystem Dashboard**, daily concept-reinforcement mini-games powered by content
cartridges, and a companion sidebar block (`block_playergames`).

📚 **[Full documentation](https://jeanlucio.github.io/moodle-local_playergames/)** — features,
the AI provider chain, the cartridge format, how PlayerQuiz and PlayerGuess work, avatars,
learning XP, ranking behavior, the educational purpose, the PlayerGames ecosystem, the full
232-case test suite, and third-party service disclosure.

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
| Moodle    | 4.5 – 5.2 |
| PHP       | 8.1+    |

### 🛠️ Installation & Configuration

> ⚠️ This plugin is not yet published on the Moodle Plugin Directory. Install manually from this repository.

1. Download the `.zip` file or clone this repository.
2. Extract the folder into your Moodle `local/` directory.
3. Rename the folder to `playergames` (if necessary).
   Final path:
   `your-moodle/local/playergames/`
4. Visit **Site administration > Notifications** to complete installation.
5. Go to **Site administration > Plugins > Local plugins > PlayerGames** to configure season, XP,
   avatar and game-rule settings.
6. Create the first season via the **Season Management** page.

### 🆘 Support

Found a bug or have a question? Open an issue on the
[issue tracker](https://github.com/jeanlucio/moodle-local_playergames/issues).

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
funcionalidades, a cadeia de provedores de IA, o formato do cartucho, como o PlayerQuiz e o
PlayerGuess funcionam, avatares, XP de aprendizado, como o ranking funciona, a finalidade
educacional, o ecossistema PlayerGames, a suíte completa de 232 testes, e a divulgação de
serviço de terceiros.

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
| Moodle     | 4.5 – 5.2 |
| PHP        | 8.1+   |

### 🛠️ Instalação e Configuração

> ⚠️ Este plugin ainda não está publicado no Diretório de Plugins do Moodle. Instale manualmente a partir deste repositório.

1. Baixe o arquivo `.zip` ou clone este repositório.
2. Extraia na pasta `local/` do seu Moodle.
3. Renomeie para `playergames` (se necessário).
   Caminho final:
   `seu-moodle/local/playergames/`
4. Acesse **Administração do site > Notificações** para concluir a instalação.
5. Vá em **Administração do site > Plugins > Plugins locais > PlayerGames** para configurar
   temporada, XP, avatares e regras dos jogos.
6. Crie a primeira temporada na página **Gerenciar Temporadas**.

### 🆘 Suporte

Encontrou um bug ou tem alguma dúvida? Abra uma issue no
[rastreador de issues](https://github.com/jeanlucio/moodle-local_playergames/issues).

### 📄 Licença

Este projeto é licenciado sob a **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

### 👤 Mantenedor

Mantido por [Jean Lúcio](https://github.com/jeanlucio).

[⬆️ Voltar ao topo](#português)
