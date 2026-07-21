# 📖 Como Usar

## Para Administradores

1. Instale o plugin e conclua o upgrade do Moodle.
2. Opcionalmente instale o **[local_aihub](https://github.com/jeanlucio/moodle-local_aihub)** se
   quiser geração de cartuchos assistida por IA com chaves pessoais/de site (ver
   [Cadeia de Provedores de IA](#ai-provider-chain)) — caso contrário, os cartuchos ainda podem
   ser criados manualmente ou gerados pelo `core_ai` do próprio Moodle.
3. Crie a primeira temporada na página **Gerenciar Temporadas**, definindo nome, datas e as
   recompensas de XP por jogo e jogadas por dia.
4. Opcionalmente ajuste a página **Escada de Níveis** — altere os limiares de XP e os títulos,
   restaure a escada padrão ou gere uma linear mais longa.
5. Faça upload ou gere um cartucho de conteúdo na página **Cartuchos**.
6. Acompanhe o impacto da gamificação no **Engajômetro**.

## Para Professores

1. Se o `local_aihub` estiver instalado, acesse a página **Minhas Chaves de IA** dele para
   configurar uma chave pessoal (opcional — as chaves de site funcionam se o admin as
   configurou).
2. Use o hub PlayerGames ou qualquer plugin Player — os recursos de IA usarão automaticamente a
   cadeia de chaves configurada.

## Para Estudantes

1. Acesse o **Player Hub** para ver seu XP, nível, posição no ranking, streak e missões do dia.
2. Jogue os minijogos diários para ganhar XP.
3. Marque ou desmarque "Aparecer no ranking" no seu perfil para controlar sua visibilidade.
