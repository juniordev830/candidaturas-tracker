<?php
declare(strict_types=1);
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

putenv('CACHE_STORE=array');
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
set_exception_handler(function (Throwable $error): void { fwrite(STDERR, (string) $error); exit(1); });
$checks = 0;
function http(string $method, string $uri, int $status, ?string $body = null, ?callable $verify = null, array $server = []) {
    global $kernel, $checks;
    $request = Request::create($uri, $method, [], [], [], $server + ['HTTP_ACCEPT'=>'application/json', 'CONTENT_TYPE'=>'application/json'], $body);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    if ($response->getStatusCode() !== $status || ($verify && !$verify($response))) {
        throw new RuntimeException("$method $uri: expected $status, got ".$response->getStatusCode().' '.$response->getContent());
    }
    $checks++;
    return $response;
}
function payload($response): array { return json_decode($response->getContent(), true, 64, JSON_THROW_ON_ERROR); }

use GX\Applications\Applications;
use GX\Applications\Storage;
$fixture = tempnam(sys_get_temp_dir(),'api-access-');
putenv('GX_APPLICATIONS_FILE='.$fixture);
$kernel->bootstrap();
$token = bin2hex(random_bytes(32));
$app['config']->set('api.token',null);
http('GET','/api/applications',503);
$app['config']->set('api.token',$token);
http('GET','/api/applications',401);
http('GET','/api/applications/summary',401,null,null,['HTTP_AUTHORIZATION'=>'Bearer wrong']);
try {
    Storage::save([Applications::create('private-1','Empresa Fictícia','PHP','2026-11-01', 'site', 123456, 'segredo pessoal')]);
    $auth = ['HTTP_AUTHORIZATION'=>'Bearer '.$token];
    foreach (['/api/applications','/api/applications/private-1','/api/applications/overdue?date=2026-11-28','/api/applications/follow-ups?date=2026-11-28'] as $uri) {
        http('GET',$uri,200,null,fn ($r) => !str_contains($r->getContent(),'segredo pessoal') && !str_contains($r->getContent(),'salario_centavos') && !str_contains($r->getContent(),'notas'),$auth);
    }
    foreach (['limit=0','offset=-1','limit[]=1','days[]=14'] as $query) {
        $path = str_starts_with($query,'days') ? '/api/applications/overdue?' : '/api/applications?';
        http('GET',$path.$query,422,null,null,$auth);
    }
    file_put_contents($fixture,'broken');
    http('GET','/api/applications',500,null,fn ($r) => payload($r) === ['error'=>'Não foi possível ler o catálogo local.'],$auth);
    Cache::store('array')->flush();
    for ($i=0;$i<60;$i++) http('GET','/api/applications',401);
    http('GET','/api/applications',429);
    http('GET','/up',200);
    http('PUT','/storage/probe',404);
    echo "API regression checks passed: $checks\n";
} finally { unlink($fixture); putenv('GX_APPLICATIONS_FILE'); }
