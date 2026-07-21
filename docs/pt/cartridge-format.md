# 📦 Formato do Cartucho

Os cartuchos de conteúdo são arquivos JSON que alimentam todos os minijogos. O mesmo pacote serve
ao PlayerQuiz, PlayerGuess, PlayerFill e PlayerBattle:

```json
{
  "name": "Fundamentos de Gamificação",
  "version": "1.0",
  "language": "pt_BR",
  "concepts": [
    {
      "term": "gamificação",
      "definition": "Uso de elementos de jogos em contextos não-lúdicos",
      "category": "fundamentos",
      "difficulty": 2
    }
  ]
}
```

- Validado pelo importador antes de salvar
- Termos para jogos estilo Wordle: filtrados por comprimento configurável (padrão 4–8 letras),
  apenas caracteres alfabéticos
- Cartuchos do tipo quiz: cada questão aceita um `generalfeedback` opcional, exibido ao jogador
  após responder
- Admin pode ter múltiplos cartuchos ativos simultaneamente
- Multi-idioma: cada cartucho declara seu `language`
