# Engenharia reversa e estudo — Candidaturas Tracker

## Objetivo e fluxo

Registro, histórico e acompanhamento de candidaturas. src/Applications.php valida cadastros e transições. Storage.php carrega ou salva o registro, e CsvExporter.php confere a preparação de relatórios.

**Tecnologias identificadas** · PHP.

**Revisão de referência** · `ddf099d30661`.

## Mapa do código

| Caminho | Papel de leitura | Símbolos selecionados |
| --- | --- | --- |
| [cli/tracker.php](../cli/tracker.php) | Implementação | `usage`, `number`, `run` |
| [main.php](../main.php) | Ponto de leitura para entrada ou composição | `transition`, `expect` |
| [src/Applications.php](../src/Applications.php) | Implementação | `GX`, `Applications`, `day`, `text`, `create`, `validate`, `validateRegistry`, `add`, `advance` |
| [src/CsvExporter.php](../src/CsvExporter.php) | Implementação | `GX`, `CsvExporter`, `safeCell`, `export`, `exportFollowUps`, `fn` |
| [src/Storage.php](../src/Storage.php) | Implementação | `GX`, `Storage`, `defaultPath`, `load`, `save` |

## Configuração e referências

Referências existentes: [ARQUITETURA.md](../ARQUITETURA.md), [docs/GUIA_DE_VALIDACAO.md](GUIA_DE_VALIDACAO.md).

## Roteiro de estudo e desenvolvimento

1. Comece pelo fluxo descrito acima e localize seus pontos de entrada no mapa. Acompanhe uma entrada válida até a transformação ou a resposta.
2. Abra um teste relacionado ao fluxo e descreva qual regra ele protege. Compare o tratamento esperado para entradas válidas, inválidas e nos limites.
3. Ao adicionar uma regra, determine qual módulo controla o dado e cubra o caso nesse nível. Em seguida, confira o efeito no comando, na API ou na interface de entrada.

**Pergunta de entrevista**

Como testar que um retorno recebido hoje altera o acompanhamento sem apagar o histórico da candidatura?

## Testes versionados

Arquivos para começar a leitura dos cenários de teste: [tests/ApplicationsTest.php](../tests/ApplicationsTest.php), [tests/CliTest.php](../tests/CliTest.php).

Os comandos e versões de runtime usados no CI ficam em [.github/workflows/ci.yml](../.github/workflows/ci.yml).

[Voltar ao README](../README.md).
