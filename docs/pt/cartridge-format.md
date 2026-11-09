# 📦 Formato do Cartucho

Os cartuchos de conteúdo são arquivos JSON que alimentam os minijogos. Um cartucho de **conceitos**
(termos e definições, como abaixo) alimenta o PlayerGuess e o PlayerFill; um cartucho de **quiz**
(questões com as respostas) alimenta o PlayerQuiz e o PlayerBattle:

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
- Termos para o PlayerGuess: filtrados por comprimento configurável (padrão 4–8 letras) e devem
  ser uma única palavra alfabética (sem espaços, dígitos ou hífens) — ver
  [Como o PlayerGuess Funciona](#playerguess)
- Cartuchos do tipo quiz: cada questão aceita um `generalfeedback` opcional, exibido ao jogador
  após responder; o PlayerQuiz e o PlayerBattle também podem sortear do **Banco de Questões do
  Moodle**, além dos cartuchos ou em conjunto com eles
- Admin pode ter múltiplos cartuchos ativos simultaneamente
- Multi-idioma: cada cartucho declara seu `language`
