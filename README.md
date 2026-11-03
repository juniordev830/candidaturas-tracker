# Candidaturas Tracker

Acompanhamento de vagas, entrevistas e versões de currículo enviadas.

## Implementado na primeira versão

**PHP — núcleo executável de domínio.** Aplica transições válidas de candidatura, preserva a versão do currículo e registra um histórico cronológico. Grava em novo arquivo, sem sobrescrever a entrada.

## Como executar

Instale PHP 8.3 CLI. Não há dependências Composer. Os comandos abaixo partem da raiz do repositório.

```sh
php main.php examples/candidatura.json triagem 2026-11-03 candidatura-atualizada.json
```

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
- [ ] Registrar etapas e retornos — ampliar o núcleo atual até o fluxo completo.
- [ ] Associar versão do currículo a cada envio — ampliar o núcleo atual até o fluxo completo.

Licença: ainda não definida.
