# 🕹️ Ecossistema PlayerGames

O PlayerGames é o hub de um ecossistema mais amplo de gamificação. Juntos, esses plugins
transformam o Moodle em uma experiência imersiva:

* **Bloco PlayerGames:** Widget companheiro de sidebar para a página inicial do site e o Painel —
  avatar equipado, nível, XP, streak, jogos do dia e posição no ranking, tudo delegado a este
  plugin. Requer o `local_playergames`.
  👉 [github.com/jeanlucio/moodle-block_playergames](https://github.com/jeanlucio/moodle-block_playergames)

* **AI Hub:** Broker compartilhado de BYOK (traga sua própria chave) — chaves pessoais e de site
  Gemini/Groq/DeepSeek/compatível-com-OpenAI, consumidas pelo PlayerGames para geração de
  cartuchos assistida por IA. Opcional: o PlayerGames continua funcionando pelo `core_ai` do
  próprio Moodle sem ele.
  👉 [github.com/jeanlucio/moodle-local_aihub](https://github.com/jeanlucio/moodle-local_aihub)

* **Bloco PlayerHUD:** XP, níveis, inventário, drops, missões, classes RPG, história, karma e
  ranking dentro de cada curso. Seu XP de curso pode opcionalmente espelhar para o pool de
  [XP de Aprendizado](#learning-xp) do PlayerGames.
  👉 [github.com/jeanlucio/moodle-block_playerhud](https://github.com/jeanlucio/moodle-block_playerhud)

* **Filtro PlayerHUD:** Permite inserir drops de itens por meio de shortcodes no conteúdo do
  curso.
  👉 [github.com/jeanlucio/moodle-filter_playerhud](https://github.com/jeanlucio/moodle-filter_playerhud)

* **Restrição de Acesso PlayerHUD:** Restringe o acesso a atividades com base no nível atual do
  aluno ou nos itens coletados.
  👉 [github.com/jeanlucio/moodle-availability_playerhud](https://github.com/jeanlucio/moodle-availability_playerhud)

* **PlayerGroup:** Permite que os alunos formem seus próprios grupos de forma autônoma
  diretamente na página da atividade.
  👉 [github.com/jeanlucio/moodle-mod_playergroup](https://github.com/jeanlucio/moodle-mod_playergroup)
