# 🤖 Cadeia de Provedores de IA

O PlayerGames resolve o provedor de IA por **nível primeiro**: uma chave configurada
explicitamente sempre vence o padrão institucional. O primeiro nível com uma chave utilizável é
usado; se uma chamada falhar (erro de rede, timeout, erro HTTP), a próxima opção disponível é
tentada automaticamente.

**Ordem de resolução:**

| Prioridade | Origem | Observações |
|------------|--------|-------------|
| 1 | **Chaves pessoais** (página *Minhas Chaves de IA*) | As chaves Gemini / Groq / compatível-com-OpenAI do próprio professor, incluindo base URL e modelo OpenAI pessoais. Tentadas primeiro, então uma chave pessoal sempre vence. |
| 2 | **Chaves globais** (configurações do PlayerGames) | Chaves que o admin cadastrou para o site inteiro. |
| 3 | **Moodle `core_ai`** | Padrão institucional, tentado **por último**. Usa os provedores configurados pelo admin em *Administração do site → IA → Provedores de IA*; no Moodle 5.2+, o `core_ai` tem fallback interno entre provedores. Nenhuma chave precisa ser cadastrada no PlayerGames. |

Dentro dos níveis 1 e 2, os provedores diretos são tentados numa ordem fixa: **Gemini → Groq →
compatível-com-OpenAI**. Cada chamada direta força o modo de saída JSON quando suportado.

> **A origem prevalece sobre o provedor.** Se existe uma chave Groq pessoal (nível 1) e o admin
> também configurou o `core_ai` (nível 3), a chave Groq pessoal é usada porque o nível 1 é
> tentado primeiro — a chave explícita sempre vence o padrão institucional.

## API de integração para outros plugins

Outros plugins do ecossistema Player podem delegar chamadas de IA ao PlayerGames sem gerenciar
chaves diretamente:

```php
use local_playergames\cartridge\ai_generator;
use local_playergames\api_key_helper;

// Verificar disponibilidade antes de exibir qualquer interface de IA
if (class_exists(ai_generator::class) && api_key_helper::has_any_key()) {
    $gen = new ai_generator();
    $result = $gen->send('Seu prompt personalizado aqui');
    // $result['success'] (bool), $result['data'] (string), $result['provider'] (string)
}
```

- `api_key_helper::has_any_key()` — retorna `true` se ao menos um provedor estiver configurado.
- `ai_generator::send(string $prompt): array` — envia um prompt livre pela cadeia completa de
  provedores e retorna o texto bruto. Use quando o plugin chamador tem seu próprio formato de
  prompt e parser de resposta.
- `ai_generator::generate(...)` — use apenas quando precisar de arrays de conceitos no formato
  `{term, definition, category, difficulty}`.
