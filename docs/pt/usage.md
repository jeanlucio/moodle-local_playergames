# 📖 Como Usar

## Para Administradores

1. Instale o plugin e conclua o upgrade do Moodle.
2. Opcionalmente instale o **[local_aihub](https://github.com/jeanlucio/moodle-local_aihub)** se
   quiser geração de cartuchos assistida por IA com chaves pessoais/de site (ver
   [Cadeia de Provedores de IA](#ai-provider-chain)) — caso contrário, os cartuchos ainda podem
   ser criados manualmente ou gerados pelo `core_ai` do próprio Moodle.
3. Crie um cartucho de conteúdo na página **Cartuchos** (envie um ou gere): os jogos precisam de algo
   para perguntar.
4. Crie a primeira temporada na página **Temporadas** e ative-a, definindo nome e datas.
5. Escolha os jogos que a temporada roda, e de onde vêm as questões deles, em **Jogos da temporada**.
6. Revise as regras (XP, níveis, quem joga) em **Regras**: os padrões funcionam, e você pode ajustá-los.
7. Acompanhe o impacto da gamificação no **Engajômetro**.

Os passos 3 a 6 são cards da aba **Administração**, nessa ordem (o Engajômetro tem aba própria); veja
[Administração](#administration).

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
