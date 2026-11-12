# Guia técnico — Candidaturas Tracker

## Estado atual

CLI PHP de acompanhamento local de candidaturas com histórico, indicadores e exportação CSV.

## Referências de código

`main.php`, `cli/tracker.php`, `src/Applications.php`, `tests/ApplicationsTest.php`, `tests/CliTest.php`.

## Validar mudanças

`php -l main.php`; `php main.php --self-test`; `php tests/ApplicationsTest.php`; `php tests/CliTest.php`.

Usar somente dados fictícios e registrar comandos executados com resultados no PR. A mera presença de um teste no repositório não comprova que ele passou.

## Cenários de regressão

Validar etapa desconhecida, data inválida, histórico ordenado, duplicidade de IDs, busca por acentos e neutralização de fórmulas no CSV.

## Próximo incremento

Arquivos JSON de candidaturas podem ser pessoais; manter fixtures sintéticas. Não incluir integração com portais nem mensagens automáticas sem consentimento.

## Critérios de revisão

- [ ] Código e documentação refletem o mesmo comportamento.
- [ ] Casos de fronteira estão protegidos por teste ou roteiro de validação.
- [ ] Arquivos e saídas geradas ficam fora do versionamento, quando contêm dados privados.
- [ ] Mudanças passam por branch e PR, sem integração automática.
