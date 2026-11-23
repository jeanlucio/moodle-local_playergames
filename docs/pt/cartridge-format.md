# 📦 Formato do Cartucho

Os cartuchos de conteúdo são arquivos JSON que alimentam os minijogos. Um cartucho de **conceitos**
(termos e definições, como abaixo) alimenta o PlayerGuess e o PlayerFill; um cartucho de **questões**
(questões com as respostas) alimenta o PlayerQuiz e o PlayerBattle:

```json
{
  "name": "Fundamentos de Gamificação",
  "version": "1.0",
  "language": "pt_BR",
  "concepts": [
    {
      "term": "gamificação",
      "definition": "Uso de elementos de jogos em contextos não-lúdicos"
    }
  ]
}
```

- Validado pelo importador antes de salvar; `category` e `difficulty` de um arquivo antigo são ignorados
- Termos para o PlayerGuess: filtrados por comprimento configurável (padrão 4–8 letras) e devem
  ser uma única palavra alfabética (sem espaços, dígitos ou hífens) — ver
  [Como o PlayerGuess Funciona](#playerguess)
- Cartuchos de questões: cada questão aceita um `generalfeedback` opcional, exibido ao jogador
  após responder
- Cartuchos de questões podem ser criados a partir de um **Banco de Questões do Moodle** (aba *Importar do
  banco de questões* da página de cartuchos): as questões de múltipla escolha de uma categoria mantida
  para reuso por todo o site são copiadas como texto simples, até o limite do importador. Questões com
  imagem, som ou vídeo ficam de fora, e o cartucho é uma cópia: mudanças feitas depois no banco não
  chegam a ele
- Cartuchos de conceitos podem ser criados a partir de um **glossário global** (aba *Glossário* da
  página de cartuchos): as entradas aprovadas viram conceitos, até o limite do importador. Glossários de
  um único curso não são oferecidos — só os que o Moodle marca como globais
- Admin pode ter múltiplos cartuchos ativos simultaneamente
- Multi-idioma: cada cartucho declara seu `language`
