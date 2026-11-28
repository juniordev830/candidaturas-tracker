# Candidaturas Tracker — API Laravel 13

API HTTP complementar para consultar o catálogo local do Candidaturas Tracker. Reutiliza as regras de negócio de `src/Applications.php` e a leitura validada de `src/Storage.php`; não duplica as transições de etapa.

**A API é somente leitura.** A CLI existente continua sendo a única interface de escrita. A API não retorna notas nem pretensão salarial, não envia contatos e não altera candidaturas.

## Requisitos e instalação

- PHP 8.3 ou superior
- Composer 2

```sh
cd laravel
composer install
cp .env.example .env
```

No PowerShell, copie o arquivo com `Copy-Item .env.example .env`. Configure `GX_APPLICATIONS_FILE` no ambiente do processo para apontar ao mesmo arquivo JSON utilizado pela CLI. Se não for configurado, a classe Storage usa seu caminho padrão em `~/.gx-candidaturas/candidaturas.json`.

## Executar e testar

```sh
composer validate --no-check-publish
composer test
php artisan route:list
php artisan serve --host=127.0.0.1 --port=8000
```

Mantenha o servidor limitado a `127.0.0.1`. Esta API não possui autenticação nem autorização e não deve ser publicada na internet.

## Endpoints

| Método | Rota | Finalidade |
|---|---|---|
| GET | `/up` | Health check do Laravel |
| GET | `/api/applications` | Lista com filtros `stage`, `q` e paginação `limit`/`offset` |
| GET | `/api/applications/summary` | Totais por etapa e situação |
| GET | `/api/applications/follow-ups?date=AAAA-MM-DD&days=14&limit=20` | Prioridades de acompanhamento |
| GET | `/api/applications/overdue?date=AAAA-MM-DD&days=14` | Candidaturas sem atualização |
| GET | `/api/applications/{id}` | Consulta um registro |

Filtros inválidos retornam HTTP 422; ID inexistente retorna 404. Os endpoints de lista e detalhe omitem notas e pretensão salarial. Os dados não são enviados a serviços externos.

## Compatibilidade

A API lê o mesmo arquivo versionado da CLI, usando a validação de `Storage::load()`. Não execute gravações externas concorrentes no arquivo JSON. Para alterar candidaturas, continue usando `php cli/tracker.php`. Não existe sincronização em tempo real ou autenticação multiusuário.
