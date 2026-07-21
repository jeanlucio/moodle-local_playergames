# 🤖 Cadeia de Provedores de IA

O armazenamento de chaves e o transporte com os provedores (Gemini, Groq, DeepSeek, compatível
com OpenAI) vivem num plugin companheiro dedicado, o
**[local_aihub](https://github.com/jeanlucio/moodle-local_aihub)**. O PlayerGames em si só monta
o prompt de geração do cartucho (tópico, idioma, quantidade de conceitos, dificuldade,
categorias) e interpreta a resposta JSON da IA de volta em conceitos, então fica livre de
gerenciamento de chaves e ainda oferece suporte à geração de cartuchos assistida por IA onde quer
que o Hub esteja instalado.

## Ordem de resolução

| Prioridade | Origem | Observações |
|------------|--------|-------------|
| 1 | **[local_aihub](https://github.com/jeanlucio/moodle-local_aihub)** | Tentado primeiro quando instalado. Resolve chaves pessoais e depois chaves de site, entre os provedores Gemini / Groq / DeepSeek / compatível-com-OpenAI. |
| 2 | **Moodle `core_ai`** | Fallback institucional, usado apenas se o Hub não estiver instalado ou não retornar uma fonte utilizável. Usa os provedores que o admin configurou em *Administração do site → IA → Provedores de IA*. |

Uma falha real de provedor (ex.: uma chave inválida cadastrada no Hub) é preservada e exibida ao
usuário — nunca é mascarada silenciosamente como "nenhuma fonte de IA disponível".

> **Instalar o `local_aihub` é opcional.** Sem ele, a geração de cartuchos via IA ainda funciona
> se o site tiver o `core_ai` configurado; o PlayerGames só perde a opção de chave pessoal (BYOK
> — cada professor trazendo sua própria chave Gemini/Groq/DeepSeek/OpenAI) que o Hub oferece.

## API de integração para outros plugins

O `cartridge\ai_generator` expõe uma API pequena e estável que outros plugins do ecossistema
podem chamar diretamente:

```php
use local_playergames\cartridge\ai_generator;

$gen = new ai_generator();
if ($gen->has_key()) {
    // Prompt livre, roteado pela mesma cadeia usada na geração de cartuchos.
    $text = $gen->generate_text('Instrução de sistema opcional', 'Seu prompt aqui');
}
```

- `has_key(): bool` — `true` se o `local_aihub` tiver uma chave utilizável ou o `core_ai` tiver
  um provedor configurado.
- `generate_text(string $system, string $user, bool $jsonmode = false): string` — envia um
  prompt livre de sistema+usuário pela cadeia e retorna o texto bruto.
- `send(string $prompt): array` — variante de nível mais baixo que retorna o array completo do
  resultado (`success`, `data`, `message`, `provider`).
- `generate(string $topic, string $language, int $count, int $difficulty, array $categorynames = [], string $context = ''): array`
  — o gerador estruturado de conceitos do cartucho; use apenas quando precisar de arrays de
  conceitos no formato `{term, definition, category, difficulty}`.

Toda geração de IA disparada pelo PlayerGames é marcada no próprio log de uso do Hub com o
componente (`local_playergames`) e uma descrição curta do que foi gerado (ex.: o tópico do
cartucho), então administradores do site conseguem ver o uso de IA por plugin consumidor a
partir do relatório de admin do Hub.
