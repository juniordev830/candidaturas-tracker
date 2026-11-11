<?php
declare(strict_types=1);

$folder = sys_get_temp_dir() . '/gx-candidates-cli-' . bin2hex(random_bytes(6));
if (!mkdir($folder, 0700, true)) {
    throw new RuntimeException('Test temp dir could not be created.');
}
$database = $folder . '/applications.json';

function cli(array $args): array
{
    global $database, $folder;
    $command = [PHP_BINARY, dirname(__DIR__) . '/cli/tracker.php', '--file', $database, ...$args];
    $pipes = [];
    $process = proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $folder
    );
    if (!is_resource($process)) {
        throw new RuntimeException('CLI could not start.');
    }
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $output, $errors];
}

function expectCli(bool $value, string $message): void
{
    if (!$value) {
        throw new RuntimeException('CLI test failed: ' . $message);
    }
}

try {
    [$exit, $text] = cli(['help']);
    expectCli($exit === 0 && str_contains($text, 'candidaturas'), 'help');

    [$exit, $text] = cli([
        'add', 'job-01', 'Empresa Fictícia', 'Backend Júnior',
        '2026-10-01', 'portal', '300000',
    ]);
    expectCli($exit === 0, 'add first record: ' . $text);

    [$exit, $text] = cli([
        'add', 'job-02', 'Loja Teste', 'Suporte N1',
        '2026-10-05',
    ]);
    expectCli($exit === 0, 'add second record');

    [$exit, $text] = cli(['list']);
    expectCli($exit === 0 && str_contains($text, 'job-01'), 'list records');
    expectCli(str_contains($text, 'job-02'), 'list second record');

    [$exit, $text] = cli(['advance', 'job-01', 'triagem', '2026-10-03']);
    expectCli($exit === 0, 'advance to screening');
    [$exit, $text] = cli(['advance', 'job-01', 'entrevista', '2026-10-06']);
    expectCli($exit === 0, 'advance to interview');
    [$exit, $text] = cli(['list', 'entrevista']);
    expectCli(str_contains($text, 'job-01') && !str_contains($text, 'job-02'),
        'filter current stage');

    [$exit, $text] = cli(['overdue', '2026-11-01', '14']);
    expectCli($exit === 0 && str_contains($text, '2'), 'stale active applications');

    [$exit, $text] = cli(['followup', '2026-11-01', '14', '1']);
    expectCli($exit === 0 && str_contains($text, 'Prioridades de retorno: 1'),
        'follow-up summary');
    expectCli(str_contains($text, 'job-02') && !str_contains($text, 'job-01'),
        'follow-up ranks oldest active record');

    [$exit, $text] = cli(['summary']);
    $summary = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
    expectCli($exit === 0 && $summary['total'] === 2, 'summary count');
    expectCli($summary['por_etapa']['entrevista'] === 1, 'stage summary');

    [$exit, $text] = cli(['csv']);
    expectCli($exit === 0 && str_contains($text, 'empresa,cargo'), 'CSV header');
    expectCli(substr_count($text, "\n") === 3, 'CSV rows');

    [$exit, $filteredCsv] = cli(['csv', 'entrevista']);
    expectCli($exit === 0 && str_contains($filteredCsv, 'job-01'), 'filtered CSV has match');
    expectCli(!str_contains($filteredCsv, 'job-02'), 'filtered CSV excludes other stages');
    expectCli(substr_count($filteredCsv, "\n") === 2, 'filtered CSV includes one row');

    [$exit, $searchCsv] = cli(['csv', 'all', 'Loja']);
    expectCli($exit === 0 && str_contains($searchCsv, 'job-02'), 'CSV supports search');
    expectCli(!str_contains($searchCsv, 'job-01'), 'CSV search excludes unmatched records');

    [$exit, $emptyCsv] = cli(['csv', 'contratada']);
    expectCli($exit === 0 && substr_count($emptyCsv, "\n") === 1,
        'empty filtered CSV still has a header');

    [$exit] = cli(['csv', 'etapa-invalida']);
    expectCli($exit !== 0, 'invalid CSV stage rejected');
    [$exit] = cli(['csv', 'all', 'Loja', 'extra']);
    expectCli($exit !== 0, 'unexpected CSV args rejected');

    [$exit] = cli([
        'add', 'job-01', 'Duplicada', 'Dev', '2026-10-01',
    ]);
    expectCli($exit !== 0, 'duplicate rejection');

    [$exit] = cli(['advance', 'job-02', 'contratada', '2026-10-06']);
    expectCli($exit !== 0, 'invalid status jump');

    [$exit] = cli(['followup', '2026-11-01', '0']);
    expectCli($exit !== 0, 'invalid follow-up window');

    [$exit, $text] = cli(['remove', 'job-01']);
    expectCli($exit === 0, 'remove record');
    [$exit, $text] = cli(['summary']);
    $report = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
    expectCli($report['total'] === 1, 'remaining count');
    expectCli($report['por_etapa']['enviada'] === 1, 'remaining stage');

    [$exit] = cli(['invalid-command']);
    expectCli($exit !== 0, 'bad command rejected');

    echo "CLI: 20 scenarios passed.\n";
} finally {
    @unlink($database);
    @rmdir($folder);
}

