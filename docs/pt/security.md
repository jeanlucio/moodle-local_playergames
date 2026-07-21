# 🔎 Divulgação de Serviço de Terceiros

O PlayerGames inclui geração de cartuchos assistida por IA, de forma opcional. Isso é totalmente
opcional — todo o conteúdo pode ser criado manualmente.

## Arquitetura

A geração via IA é roteada pelo plugin companheiro
**[local_aihub](https://github.com/jeanlucio/moodle-local_aihub)** quando instalado, que é o
dono do armazenamento de chaves e do transporte com o provedor; se o Hub não estiver instalado, o
PlayerGames recorre ao subsistema `core_ai` do próprio Moodle, usando os provedores que o
administrador do site configurou lá.

## Provedores suportados (via local_aihub)

- **Google Gemini** — https://ai.google.dev/
- **Groq** — https://console.groq.com/
- **DeepSeek** — https://platform.deepseek.com/
- **APIs compatíveis com OpenAI** — Qualquer provedor que siga o formato da API OpenAI (ex.:
  OpenRouter, modelos locais via LM Studio, proxy Ollama, etc.)

Esses serviços seguem seus próprios termos de uso e políticas de privacidade. A divulgação
completa com os IDs exatos de modelo e destinos de dados está documentada na
[própria Divulgação de Serviço de Terceiros do local_aihub](https://jeanlucio.github.io/moodle-local_aihub/#third-party-disclosure).

## Transmissão de dados

Quando a geração via IA é usada, o prompt montado pelo PlayerGames (tópico, idioma, dificuldade,
categorias e qualquer texto de referência opcional fornecido pelo professor) é passado para
qualquer que seja a fonte que resolveu a requisição (o Hub ou o `core_ai`) para processamento. O
PlayerGames:
- Não armazena prompts nem respostas da IA além dos conceitos do cartucho criados dentro do
  Moodle
- Nunca inclui dados de estudante em um prompt de IA

Nenhuma comunicação externa ocorre sem ativação explícita de um recurso de IA.

## Custo

Nenhum é exigido pelo próprio PlayerGames. Se o `local_aihub` estiver instalado, qualquer custo
é o que o provedor cobrar através das chaves BYOK desse plugin; sem ele, o PlayerGames recorre
ao `core_ai` do Moodle, que pode ser gratuito se o administrador do site tiver configurado um
provedor institucional sem custo.

## Credenciais de Demonstração

Não aplicável — nenhuma credencial é exigida para instalar ou usar o PlayerGames; a geração de
cartuchos assistida por IA é totalmente opcional.
