<?php
declare(strict_types=1);

use GX\Applications\Applications;
use GX\Applications\Storage;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/../vendor/autoload.php';

$fixture = tempnam(sys_get_temp_dir(), 'gx-app-api-');
if ($fixture === false) {
    throw new RuntimeException('Não foi possível criar fixture temporária.');
}
unlink($fixture);
putenv('GX_APPLICATIONS_FILE='.$fixture);

try {
    Storage::save([
        Applications::create('demo-001', 'Empresa Fictícia', 'Desenvolvedor PHP', '2026-11-01'),
        Applications::create('demo-002', 'Outra Empresa', 'Analista', '2026-11-20'),
    ]);

    $app = require __DIR__.'/../bootstrap/app.php';
    $kernel = $app->make(Kernel::class);

    function check(Kernel $kernel, string $uri, int $expectedStatus, ?callable $verify = null): void
    {
        $request = Request::create($uri, 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        $payload = json_decode($response->getContent(), true, 64, JSON_THROW_ON_ERROR);
        if ($response->getStatusCode() !== $expectedStatus ||
            ($verify !== null && !$verify($payload))) {
            throw new RuntimeException("$uri: esperado HTTP $expectedStatus, recebido ".$response->getStatusCode());
        }
    }

    check($kernel, '/api/applications?stage=enviada&q=Fictícia', 200,
        static fn (array $data): bool => $data['total'] === 1 && $data['items'][0]['id'] === 'demo-001');
    check($kernel, '/api/applications/summary', 200,
        static fn (array $data): bool => $data['total'] === 2 && $data['ativas'] === 2);
    check($kernel, '/api/applications/follow-ups?date=2026-11-28&days=14', 200,
        static fn (array $data): bool => count($data['items']) === 1 && $data['items'][0]['id'] === 'demo-001');
    check($kernel, '/api/applications/overdue?date=2026-11-28&days=14', 200,
        static fn (array $data): bool => count($data['items']) === 1);
    check($kernel, '/api/applications/inexistente', 404);
    check($kernel, '/api/applications?stage[]=enviada', 422);

    echo "Laravel API smoke tests passed\n";
} finally {
    if (is_file($fixture)) {
        unlink($fixture);
    }
    putenv('GX_APPLICATIONS_FILE');
}
