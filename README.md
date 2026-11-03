# Candidaturas Tracker

Acompanhamento de vagas, entrevistas e versões de currículo enviadas.

## Implementado na primeira versão

**PHP — núcleo executável de domínio.** Aplica transições válidas de candidatura, preserva a versão do currículo e registra um histórico cronológico. Grava em novo arquivo, sem sobrescrever a entrada. A opção `--resumo` calcula, sem modificar o arquivo, há quantos dias a candidatura está na etapa atual e sinaliza a necessidade de acompanhamento.

## Como executar

Instale PHP 8.3 CLI. Não há dependências Composer. Os comandos abaixo partem da raiz do repositório.

```sh
php main.php examples/candidatura.json triagem 2026-11-03 candidatura-atualizada.json
php main.php --resumo candidatura-atualizada.json 2026-11-20 14
```

## Resumo de acompanhamento

`--resumo ARQUIVO DATA [LIMITE_DIAS]` retorna JSON com a etapa, número de eventos, última movimentação, dias na etapa e `acompanhar` (booleano). O limite padrão é **14 dias** e aceita 1–3650. Datas devem ser `AAAA-MM-DD`. O histórico é validado quanto às transições e à ordem cronológica. Em candidaturas **sem histórico**, a data da entrada não é conhecida: `dias_na_etapa` é `null` e a candidatura não é marcada como atrasada. Etapas terminais (`contratada`, `rejeitada`, `retirada`) não são sinalizadas para acompanhamento. O relatório é apenas informativo; não envia mensagens a recrutadores ou e-mails.

## Testes

```sh
php main.php --self-test
```

Os testes cobrem invariantes e entradas inválidas. A preparação local não dispõe deste runtime; o workflow em `.github/workflows/ci.yml` executa a validação no GitHub Actions. O resultado de cada execução está na aba Actions, associado ao commit.

## Organização e limites

Código e exemplos: `main.php e examples/candidatura.json`. Os dados de exemplo são fictícios. As funções de domínio são separadas da leitura de argumentos no mesmo arquivo para manter a primeira versão pequena e revisável.

Ainda não tem painel, login, envio de currículos ou banco. Laravel, Livewire e MariaDB estão planejados. Os dados de exemplo são fictícios.

A proposta completa de evolução está em `ARQUITETURA.md`. As tecnologias propostas nessa arquitetura não devem ser interpretadas como dependências já implementadas.

## Próximas entregas

- [ ] Cadastrar candidaturas — ampliar o núcleo atual até o fluxo completo.
- [x] Registrar etapas no histórico e gerar resumo de acompanhamento no terminal.
- [ ] Armazenar contatos e ações de retorno com dados autorizados.
- [ ] Associar versão do currículo a cada envio — ampliar o núcleo atual até o fluxo completo.

Licença: ainda não definida.
