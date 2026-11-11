<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Applications.php';
require_once dirname(__DIR__) . '/src/Storage.php';
require_once dirname(__DIR__) . '/src/CsvExporter.php';

use GX\Applications\Applications;
use GX\Applications\Storage;
use GX\Applications\CsvExporter;

function usage(): string
{
    return implode("\n", [
        'Rastreador local de candidaturas — PHP 8.3',
        '  php cli/tracker.php [--file caminho.json] COMANDO [argumentos]',
        '',
        'Comandos:',
        '  add ID EMPRESA CARGO AAAA-MM-DD [ORIGEM] [SALARIO_CENTAVOS]',
        '  advance ID ETAPA AAAA-MM-DD',
        '  remove ID',
        '  list [ETAPA|all] [BUSCA]',
        '  overdue AAAA-MM-DD [DIAS]',
        '  followup AAAA-MM-DD [DIAS] [LIMITE]',
        '  summary',
        '  csv [ETAPA|all] [BUSCA]',
        '  help',
        '',
        'Etapas válidas: enviada, triagem, entrevista, proposta, contratada, rejeitada, retirada.',
        'A exportação CSV vai para stdout e não grava dados no repositório.',
        '',
    ]);
}

function number(string $value, string $field): int
{
    if (preg_match('/^[0-9]+$/D', $value) !== 1) {
        throw new InvalidArgumentException("$field deve ser inteiro não negativo.");
    }
    $number = filter_var($value, FILTER_VALIDATE_INT);
    if ($number === false || $number < 0) {
        throw new InvalidArgumentException("$field fora do intervalo.");
    }
    return $number;
}

function run(array $args): int
{
    $path = null;
    if (($args[0] ?? '') === '--file') {
        if (!isset($args[1]) || trim($args[1]) === '') {
            throw new InvalidArgumentException('Informe o caminho após --file.');
        }
        $path = $args[1];
        $args = array_slice($args, 2);
    }

    $command = $args[0] ?? 'help';
    $values = array_slice($args, 1);
    if ($command === 'help' || $command === '--help') {
        echo usage();
        return 0;
    }

    $registry = Storage::load($path);
    switch ($command) {
        case 'add':
            if (count($values) < 4 || count($values) > 6) {
                throw new InvalidArgumentException('Uso: add ID EMPRESA CARGO DATA [ORIGEM] [SALARIO]');
            }
            $item = Applications::create(
                $values[0], $values[1], $values[2], $values[3],
                $values[4] ?? 'outro',
                isset($values[5]) ? number($values[5], 'salário') : null
            );
            Storage::save(Applications::add($registry, $item), $path);
            echo 'Candidatura adicionada: ' . $item['id'] . "\n";
            return 0;

        case 'advance':
            if (count($values) !== 3) {
                throw new InvalidArgumentException('Uso: advance ID ETAPA DATA');
            }
            Storage::save(
                Applications::advanceById($registry, $values[0], $values[1], $values[2]),
                $path
            );
            echo "Etapa atualizada: {$values[0]} => {$values[1]}\n";
            return 0;

        case 'remove':
            if (count($values) !== 1) {
                throw new InvalidArgumentException('Uso: remove ID');
            }
            Storage::save(Applications::remove($registry, $values[0]), $path);
            echo "Candidatura excluída: {$values[0]}\n";
            return 0;

        case 'list':
            if (count($values) > 2) {
                throw new InvalidArgumentException('Uso: list [ETAPA] [BUSCA]');
            }
            $selected = Applications::filter($registry, $values[0] ?? 'all', $values[1] ?? '');
            if (!$selected) {
                echo "Nenhuma candidatura encontrada.\n";
            }
            foreach ($selected as $item) {
                echo implode(' | ', [
                    $item['id'], $item['empresa'], $item['cargo'],
                    $item['etapa'], $item['data_envio'],
                ]) . "\n";
            }
            return 0;

        case 'overdue':
            if (count($values) < 1 || count($values) > 2) {
                throw new InvalidArgumentException('Uso: overdue DATA [DIAS]');
            }
            $days = isset($values[1]) ? number($values[1], 'dias') : 14;
            $late = Applications::overdue($registry, $values[0], $days);
            echo 'Sem atualização há pelo menos ' . $days . ' dias: ' . count($late) . "\n";
            foreach ($late as $item) {
                echo $item['id'] . ' | ' . $item['empresa'] . ' | ' .
                    $item['etapa'] . "\n";
            }
            return 0;

        case 'followup':
            if (count($values) < 1 || count($values) > 3) {
                throw new InvalidArgumentException('Uso: followup DATA [DIAS] [LIMITE]');
            }
            $days = isset($values[1]) ? number($values[1], 'dias') : 14;
            $limit = isset($values[2]) ? number($values[2], 'limite') : 20;
            $followUps = Applications::followUps($registry, $values[0], $days, $limit);
            echo 'Prioridades de retorno: ' . count($followUps) . "\n";
            foreach ($followUps as $item) {
                echo implode(' | ', [
                    $item['id'], $item['empresa'], $item['cargo'], $item['etapa'],
                    $item['ultima_atualizacao'], $item['dias_sem_atualizacao'] . ' dias',
                ]) . "\n";
            }
            return 0;

        case 'summary':
            if ($values !== []) {
                throw new InvalidArgumentException('summary não recebe argumentos.');
            }
            $summary = Applications::summary($registry);
            echo json_encode($summary,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
            return 0;

        case 'csv':
            if (count($values) > 2) {
                throw new InvalidArgumentException('Uso: csv [ETAPA] [BUSCA]');
            }
            // Reutiliza a mesma validação da lista; o CSV nunca altera registros.
            $selected = Applications::filter(
                $registry, $values[0] ?? 'all', $values[1] ?? ''
            );
            echo CsvExporter::export($selected);
            return 0;

        default:
            throw new InvalidArgumentException('Comando desconhecido: ' . $command);
    }
}

try {
    exit(run(array_slice($argv, 1)));
} catch (Throwable $error) {
    fwrite(STDERR, 'Erro: ' . $error->getMessage() . "\n");
    exit(2);
}

