# candidaturas-tracker — API Laravel 13

Proteção por token da API de consulta às candidaturas.

## Instalar e executar

Requer PHP 8.3 ou 8.4, extensões do Laravel e Composer 2. A dependência é `laravel/framework:^13.0`; o `composer.lock` registra a resolução revisada. A aplicação PHP original continua independente.

```sh
cd laravel
composer install --no-interaction --prefer-dist
composer validate --no-check-publish
composer audit
php artisan route:list --json
composer test
php artisan serve --host=127.0.0.1 --port=8000
```

Copie `.env.example` para `.env` somente em instalação nova. Não versione esse arquivo nem segredos. `CACHE_STORE=file` é o padrão para manter o contador de requisições entre chamadas. O diretório `storage/framework/cache/data` precisa ser gravável. `CACHE_STORE=array` é reservado aos testes em processo único; ele não limita chamadas entre processos web.

## Contrato HTTP

A API continua somente leitura e reutiliza `Applications` e `Storage`. A CLI mantém a escrita e o formato do arquivo. Notas e pretensão salarial são omitidas das respostas. O armazenamento pode ser selecionado por `GX_APPLICATIONS_FILE`.

Gere um segredo local com `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` e configure `LOCAL_API_TOKEN` no `.env` privado. Não há token padrão nem senha compartilhada no código. Todos os endpoints `/api/*` exigem `Authorization: Bearer <token>`; sem configuração de pelo menos 32 caracteres retornam 503 e credencial ausente ou errada retorna 401. `GET /up` permanece público.

| Método | Rota | Uso |
|---|---|---|
| GET | `/api/applications` | Filtros `stage`, `q`, `limit` (1–100) e `offset` (0–5000) |
| GET | `/api/applications/summary` | Totais por etapa |
| GET | `/api/applications/follow-ups` | `date`, `days` (1–365), `limit` (1–100) |
| GET | `/api/applications/overdue` | `date`, `days` |
| GET | `/api/applications/{id}` | Consulta por ID |

Envie o cabeçalho Bearer usando seu cliente HTTP. A mudança exige configurar o token em clientes anteriores. Esta autenticação representa um único proprietário local; não oferece contas, papéis nem isolamento entre múltiplos usuários.


## Arquitetura e limites

`bootstrap/app.php` registra rotas, respostas JSON de erro e limite de 60 chamadas por minuto por IP. Respostas 429 incluem `Retry-After`. `config/filesystems.php` desativa rotas de upload/download desnecessárias. As rotas desta API não usam Eloquent, sessões, filas, banco ou armazenamento de uploads, pois essas funções não são necessárias ao escopo. As classes do domínio são reutilizadas por autoload do Composer, sem copiar regras.

Entradas inválidas retornam 422, formato não suportado 415 e corpos acima do limite 413 nos endpoints JSON. Erros inesperados não devem expor stack traces: mantenha `APP_DEBUG=false`. Não há CORS personalizado para integrar uma SPA pública. Use o servidor em loopback; publicação exige uma decisão separada sobre identidade de usuários, autorização, TLS, proxy confiável e operação.

## Verificação

`composer test` executa smoke tests e regressões diretamente pelo kernel HTTP. As regressões cobrem casos válidos e inválidos, proteção de saída, limites, 429 e ausência das rotas de armazenamento. Exceções do executor encerram o processo com código 1. O workflow `Laravel API` instala dependências pelo lock, executa auditoria, lista rotas e roda os testes em PHP 8.3 e 8.4. A suíte original da raiz continua no workflow existente.

O bootstrap mínimo usa um executor HTTP independente; `composer test` é o comando de teste deste módulo. Não há PHPUnit nem comando `artisan test` instalado aqui.
