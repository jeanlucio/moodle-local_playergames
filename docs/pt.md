---
layout: default
title: Documentação PlayerGames
lang: pt
---

[![Moodle Plugin CI](https://github.com/jeanlucio/moodle-local_playergames/actions/workflows/ci.yml/badge.svg)](https://github.com/jeanlucio/moodle-local_playergames/actions/workflows/ci.yml)
![Moodle](https://img.shields.io/badge/Moodle-4.5%2B-orange?style=flat-square&logo=moodle&logoColor=white)
![License](https://img.shields.io/badge/License-GPLv3-blue?style=flat-square)
![Status](https://img.shields.io/badge/Status-In%20Development-yellow?style=flat-square)
[![PlayerGames Ecosystem](https://img.shields.io/badge/PlayerGames-Ecosystem-6f42c1?style=flat-square&logo=gamepad&logoColor=white)](https://moodle.org/plugins/browse.php?list=contributor&id=3970322)
![Core Component](https://img.shields.io/badge/Role-Central_Hub-198754?style=flat-square)

> ⚠️ **Este plugin está em desenvolvimento ativo.** Ainda não foi publicado no Diretório de
> Plugins do Moodle. Algumas funcionalidades descritas abaixo são planejadas e ainda não estão
> implementadas.

O **PlayerGames** (`local_playergames`) é o hub central do ecossistema de gamificação
PlayerGames para o Moodle. Serve a quatro propósitos principais:

1. **Dashboard do Ecossistema** — visão geral visual de todos os plugins Player instalados no
   site, com status, dependências e links de acesso rápido.
2. **Hub Central de Chaves de IA** — um único lugar onde administradores e professores configuram
   chaves de API (Gemini, Groq, compatível com OpenAI) compartilhadas entre todos os plugins
   Player.
3. **Player Hub** — plataforma de gamificação site-wide para estudantes e/ou outros usuários do
   Moodle (professores, managers, administradores), com XP, níveis, temporadas, missões,
   conquistas e streak diário. O administrador configura quais grupos participam.
4. **Minijogos Diários** — quatro minijogos de reforço de conceitos e um check-in diário,
   alimentados por cartuchos de conteúdo (JSON) — funcionando como um loop de aprendizado estilo
   Duolingo.

Use a barra lateral para ir a qualquer seção desta página.

Código-fonte: [github.com/jeanlucio/moodle-local_playergames](https://github.com/jeanlucio/moodle-local_playergames)

---

<span id="features"></span>
{% include_relative pt/features.md %}

<span id="ai-provider-chain"></span>
{% include_relative pt/ai-provider-chain.md %}

<span id="cartridge-format"></span>
{% include_relative pt/cartridge-format.md %}

<span id="playerquiz"></span>
{% include_relative pt/playerquiz.md %}

<span id="educational-purpose"></span>
{% include_relative pt/educational-purpose.md %}

<span id="ecosystem"></span>
{% include_relative pt/ecosystem.md %}

<span id="requirements"></span>
{% include_relative pt/requirements.md %}

<span id="installation"></span>
{% include_relative pt/installation.md %}

<span id="usage"></span>
{% include_relative pt/usage.md %}

<span id="testing"></span>
{% include_relative pt/testing.md %}

<span id="security"></span>
{% include_relative pt/security.md %}

<span id="license"></span>
{% include_relative pt/license.md %}
