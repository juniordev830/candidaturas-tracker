<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Applications.php';
require_once dirname(__DIR__) . '/src/Storage.php';
require_once dirname(__DIR__) . '/src/CsvExporter.php';

use GX\Applications\Applications;
use GX\Applications\Storage;
use GX\Applications\CsvExporter;

$count = 0;

function ok(bool $value, string $description): void
{
    global $count;
    $count++;
    if (!$value) {
        throw new RuntimeException('Teste falhou: ' . $description);
    }
}

function fails(callable $action, string $description): void
{
    global $count;
    $count++;
    try {
        $action();
    } catch (InvalidArgumentException | RuntimeException | TypeError $error) {
        return;
    }
    throw new RuntimeException('Erro não lançado: ' . $description);
}

function example(string $id = 'vaga-1', string $date = '2026-10-01'): array
{
    return Applications::create(
        $id, 'Empresa Fictícia', 'Desenvolvedor Junior', $date,
        'portal', 250000, 'Registro fictício'
    );
}

$initial = example();
ok($initial['etapa'] === 'enviada', 'initial stage');
ok($initial['historico'] === [], 'no events');
ok($initial['salario_centavos'] === 250000, 'salary cents');
ok(Applications::validate($initial) === $initial, 'validated same record');
ok(Applications::day('2024-02-29') === '2024-02-29', 'leap year');
fails(static fn() => Applications::day('2026-02-29'), 'invalid leap day');
fails(static fn() => Applications::day('2026-2-1'), 'date format');
fails(static fn() => Applications::create('bad id', 'X', 'Dev', '2026-10-01'), 'bad id');
fails(static fn() => Applications::create('x', ' ', 'Dev', '2026-10-01'), 'empty company');
fails(static fn() => Applications::create('x', 'X', 'Dev', '2026-10-01', 'portal', -1), 'negative salary');
fails(static fn() => Applications::create('x', "Bad\nCompany", 'Dev', '2026-10-01'), 'controls');

// Invalid UTF-8 must be rejected before JSON serialization.
fails(static fn() => Applications::create('utf8-a', chr(0xFF), 'Dev', '2026-10-01'),
    'malformed UTF-8 in company');
fails(static fn() => Applications::create('utf8-b', 'Empresa', 'Dev', '2026-10-01',
    'portal', null, chr(0xC3) . chr(0x28)),
    'malformed UTF-8 in notes');
$unicode = Applications::create('unicode-ok', 'São José', 'Engenheira', '2026-10-01',
    'portal', null, 'Observação válida');
ok(Applications::validate($unicode) === $unicode, 'valid accented UTF-8 survives validation');

// Validate strict state-machine transitions without changing originals.
$screening = Applications::advance($initial, 'triagem', '2026-10-02');
ok($screening['etapa'] === 'triagem', 'first step');
ok($initial['etapa'] === 'enviada', 'immutable previous record');
ok(count($screening['historico']) === 1, 'one history event');
$interview = Applications::advance($screening, 'entrevista', '2026-10-03');
$offer = Applications::advance($interview, 'proposta', '2026-10-04');
$hired = Applications::advance($offer, 'contratada', '2026-10-05');
ok($hired['etapa'] === 'contratada', 'hired stage');
ok(count($hired['historico']) === 4, 'four events');
ok(Applications::validate($hired) === $hired, 'valid roundtrip');
fails(static fn() => Applications::advance($initial, 'contratada', '2026-10-02'), 'invalid jump');
fails(static fn() => Applications::advance($screening, 'proposta', '2026-10-03'), 'invalid jump 2');
fails(static fn() => Applications::advance($screening, 'entrevista', '2026-10-01'), 'backdated event');
fails(static fn() => Applications::advance($hired, 'retirada', '2026-10-06'), 'terminal stage');
fails(static fn() => Applications::validate([
    ...$hired, 'etapa' => 'triagem',
]), 'invalid final stage');
fails(static fn() => Applications::validate([
    ...$hired, 'unexpected' => 'value',
]), 'unknown field');
fails(static fn() => Applications::validate([
    ...$hired, 'historico' => [
        ['de' => 'enviada', 'para' => 'contratada', 'data' => '2026-10-02']
    ],
]), 'invalid history');

// Registry, duplicate prevention, search and lifecycle updates.
$records = Applications::add([], $initial);
ok(count($records) === 1, 'registry insertion');
fails(static fn() => Applications::add($records, $initial), 'duplicate ID');
$records = Applications::add($records, example('vaga-2', '2026-10-08'));
ok(count($records) === 2, 'second record');
$updated = Applications::advanceById($records, 'vaga-2', 'triagem', '2026-10-09');
ok($updated[1]['etapa'] === 'triagem', 'transition by ID');
ok($records[1]['etapa'] === 'enviada', 'immutable registry');
fails(static fn() => Applications::advanceById($records, 'missing', 'triagem', '2026-10-09'), 'missing ID');
ok(count(Applications::filter($updated, 'triagem')) === 1, 'filter stage');
ok(count(Applications::filter($updated, 'all', 'desenvolvedor')) === 2, 'filter job');
ok(count(Applications::filter($updated, 'all', 'vaga-2')) === 1, 'filter ID');
ok(Applications::filter($updated)[0]['id'] === 'vaga-2', 'newest first');
fails(static fn() => Applications::filter($updated, 'unknown'), 'unknown stage');

$removed = Applications::remove($updated, 'vaga-1');
ok(count($removed) === 1, 'remove selected application');
ok(count($updated) === 2, 'do not mutate list');
fails(static fn() => Applications::remove($updated, 'missing'), 'remove unknown');

$secondScreening = Applications::advance(example('vaga-2'), 'triagem', '2026-10-02');
$report = Applications::summary([$hired, $secondScreening]);
ok($report['total'] === 2, 'total summary');
ok($report['ativas'] === 1 && $report['encerradas'] === 1, 'status partition');
ok($report['por_etapa']['contratada'] === 1, 'hired count');

$thirdInitial = example('vaga-3');
$due = Applications::overdue([$thirdInitial, $hired, $secondScreening], '2026-11-01', 14);
ok(count($due) === 2, 'only active, stale applications');
ok(count(Applications::overdue([$initial], '2026-10-05', 14)) === 0, 'not due before interval');
fails(static fn() => Applications::overdue([$initial], '2026-11-01', 0), 'bad due interval');

// Spreadsheet export: all rows are quoted/escaped safely by fputcsv.
$malicious = Applications::create(
    'candidate-9', '=SUM(A1:A2)', 'QA, backend', '2026-10-01'
);
$csv = CsvExporter::export([$malicious, $screening]);
ok(str_contains($csv, 'empresa,cargo'), 'CSV heading');
ok(str_contains($csv, "'=SUM(A1:A2)"), 'CSV formula prefix');
ok(str_contains($csv, '"QA, backend"'), 'CSV embedded comma');
ok(substr_count($csv, "\n") === 3, 'CSV row count');
ok(substr_count(CsvExporter::export([]), "\n") === 1, 'empty CSV has header');

// Private, atomic JSON snapshots.
$folder = sys_get_temp_dir() . '/gx-app-tests-' . bin2hex(random_bytes(6));
if (!mkdir($folder, 0700, true)) {
    throw new RuntimeException('Cannot create temporary test folder');
}
$file = $folder . '/private/candidaturas.json';
try {
    ok(Storage::load($file) === [], 'missing catalog is empty');
    ok(Storage::save([$screening], $file) === 1, 'one record saved');
    ok(Storage::load($file) === [$screening], 'read after write');
    $original = file_get_contents($file);
    fails(static fn() => Storage::save([$screening, $screening], $file), 'duplicate store');
    ok(file_get_contents($file) === $original, 'failed save preserves original');
    file_put_contents($file, '{invalid');
    fails(static fn() => Storage::load($file), 'invalid JSON rejected');
    file_put_contents($file, '{"version":2,"applications":[]}');
    fails(static fn() => Storage::load($file), 'unknown version rejected');
    file_put_contents($file, str_repeat('x', 1_000_001));
    fails(static fn() => Storage::load($file), 'oversized JSON rejected');
} finally {
    @unlink($file);
    @rmdir(dirname($file));
    @rmdir($folder);
}

echo "Application tracker: {$count} checks passed.\n";
