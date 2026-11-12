# Candidaturas Tracker

Ferramenta local em **PHP 8.3** para acompanhar candidaturas de emprego, controlar etapas de seleção e consultar registros que estão há muito tempo sem atualização.

A implementação original de transições em `main.php` foi mantida. A expansão adiciona biblioteca reutilizável, persistência local, CLI e testes independentes.

## Funcionalidades

- Cadastro de candidaturas com identificador, empresa, cargo, origem, data de envio e pretensão opcional em centavos.
- Transições validadas: `enviada → triagem → entrevista → proposta → contratada`, com rejeição ou retirada nas etapas permitidas.
- Histórico cronológico das transições, sem permitir datas anteriores ao último evento.
- Filtros por etapa e pesquisa por empresa, cargo ou ID, sem diferenciar letras maiúsculas de minúsculas, inclusive acentos em português (por exemplo, `ÁGUA` é encontrada por `água`). A busca mantém a diferença entre termos com e sem acento (`ação` e `acao`).
- Lista de candidaturas ativas há determinado número de dias sem atualização; não envia mensagens automaticamente.
- Priorização de retornos pendentes por dias sem atualização, sem enviar contatos ou alterar registros.
- Indicadores de candidaturas ativas, encerradas e distribuição por etapa.
- Exportação CSV para planilhas com neutralização de campos que parecem fórmulas.
- Arquivo JSON local versionado, com gravação usando arquivo temporário e substituição.
- Testes de regras de negócio, importação, consultas, exportação e comandos de terminal.

## Requisitos

PHP **8.3 CLI**, sem Composer e sem banco de dados. Para verificar:

```bash
php -l main.php
php main.php --self-test
php tests/ApplicationsTest.php
php tests/CliTest.php
```

## Exemplo no terminal

Use apenas dados fictícios ao experimentar:

```bash
php cli/tracker.php add teste-001 "Empresa Exemplo" "Desenvolvedor PHP" 2026-11-08 portal 300000
php cli/tracker.php add teste-002 "Loja Fictícia" "Suporte N1" 2026-11-08
php cli/tracker.php advance teste-001 triagem 2026-11-09
php cli/tracker.php list
php cli/tracker.php list triagem
php cli/tracker.php overdue 2026-11-30 14
php cli/tracker.php followup 2026-11-30 14 10
php cli/tracker.php summary
php cli/tracker.php csv > candidaturas-privadas.csv
php cli/tracker.php remove teste-002
```

O valor `300000` significa **R$ 3.000,00** em centavos, e não uma garantia de salário. O arquivo CSV pode conter dados de vagas e empresas; mantenha-o privado.

`followup` retorna as candidaturas ativas que ultrapassaram a janela informada,
da mais antiga para a mais recente. A data é sempre explícita no comando para
que a lista seja reproduzível; nenhum contato é enviado automaticamente.

Para utilizar um arquivo diferente, use `--file caminho.json` antes do comando:

```bash
php cli/tracker.php --file /tmp/candidaturas-teste.json summary
```

## Privacidade e limites

Por padrão, o catálogo reside em `~/.gx-candidaturas/candidaturas.json` (ou no caminho definido por `GX_APPLICATIONS_FILE`). É um protótipo local sem criptografia, autenticação ou acesso multiusuário; não armazene senhas nem dados reais de recrutadores no GitHub.

O sistema valida até **5.000 candidaturas**, **100 eventos por candidatura** e um arquivo de até **1 MB**. A busca textual é simples; não implementa sincronização com plataformas de vagas nem notificação de empregadores.

## API reutilizável

```php
<?php
require 'src/Applications.php';

use GX\Applications\Applications;

$registro = Applications::create(
    'teste-001', 'Empresa Fictícia', 'Desenvolvedor PHP', '2026-11-08'
);
$registro = Applications::advance($registro, 'triagem', '2026-11-09');
print_r(Applications::summary([$registro]));
```

## Licença

Ainda não definida.

## Roteiro de qualidade

Consulte o [guia de validação e manutenção](docs/GUIA_DE_VALIDACAO.md) para reproduzir testes, entender as limitações e revisar mudanças com segurança.
