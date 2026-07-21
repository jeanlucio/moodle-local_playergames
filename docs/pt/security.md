# 🔎 Divulgação de Serviço de Terceiros

O PlayerGames inclui recursos opcionais de geração de conteúdo via IA (cartuchos). Esses recursos
são totalmente opcionais — todo o conteúdo pode ser criado manualmente.

## Provedores suportados

- **Google Gemini** — https://ai.google.dev/
- **Groq** — https://console.groq.com/
- **APIs compatíveis com OpenAI** — Qualquer provedor que siga o formato da API OpenAI (ex.:
  OpenRouter, modelos locais via LM Studio, proxy Ollama, etc.)

Esses serviços seguem seus próprios termos de uso e políticas de privacidade.

## Transmissão de dados

Quando o recurso de IA é utilizado, os prompts informados são enviados ao provedor selecionado
para processamento. O plugin:
- Não armazena prompts nem respostas da IA
- Apenas salva os conceitos do cartucho criados dentro do Moodle

Nenhuma comunicação externa ocorre sem ativação explícita de um recurso de IA.
