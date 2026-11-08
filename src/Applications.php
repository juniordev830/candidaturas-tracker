<?php
declare(strict_types=1);

namespace GX\Applications;

use DateTimeImmutable;
use InvalidArgumentException;

final class Applications
{
    public const TRANSITIONS = [
        'enviada' => ['triagem', 'rejeitada', 'retirada'],
        'triagem' => ['entrevista', 'rejeitada', 'retirada'],
        'entrevista' => ['proposta', 'rejeitada', 'retirada'],
        'proposta' => ['contratada', 'retirada'],
        'contratada' => [],
        'rejeitada' => [],
        'retirada' => [],
    ];

    public const MAX_RECORDS = 5000;
    public const MAX_AMOUNT_CENTS = 100_000_000_000;

    public static function day(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Data inválida: use AAAA-MM-DD.');
        }
        return $value;
    }

    private static function text(mixed $value, string $field, int $limit): string
    {
        if (!is_string($value) || strlen($value) > $limit * 4) {
            throw new InvalidArgumentException("Campo $field inválido.");
        }
        $clean = trim($value);
        if ($clean === '' || mbLength($clean) > $limit ||
            preg_match('/[\x00-\x1F\x7F]/', $clean)) {
            throw new InvalidArgumentException("Campo $field vazio, grande demais ou com controle.");
        }
        return $clean;
    }

    public static function create(
        string $id,
        string $company,
        string $role,
        string $submittedOn,
        string $source = 'outro',
        ?int $salaryCents = null,
        string $notes = ''
    ): array {
        if (!preg_match('/^[a-zA-Z0-9_-]{1,50}$/D', $id)) {
            throw new InvalidArgumentException('ID inválido.');
        }
        self::day($submittedOn);
        if ($salaryCents !== null &&
            ($salaryCents < 0 || $salaryCents > self::MAX_AMOUNT_CENTS)) {
            throw new InvalidArgumentException('Salário esperado em centavos inválido.');
        }
        if (strlen($notes) > 1000 || preg_match('/[\x00-\x08\x0B-\x1F]/', $notes)) {
            throw new InvalidArgumentException('Observações inválidas.');
        }
        return [
            'id' => $id,
            'empresa' => self::text($company, 'empresa', 120),
            'cargo' => self::text($role, 'cargo', 120),
            'origem' => self::text($source, 'origem', 60),
            'etapa' => 'enviada',
            'data_envio' => $submittedOn,
            'salario_centavos' => $salaryCents,
            'notas' => $notes,
            'historico' => [],
        ];
    }

    public static function validate(array $record): array
    {
        $required = [
            'id', 'empresa', 'cargo', 'origem', 'etapa',
            'data_envio', 'salario_centavos', 'notas', 'historico',
        ];
        sort($required);
        $actual = array_keys($record);
        sort($actual);
        if ($actual !== $required) {
            throw new InvalidArgumentException('Registro contém campos ausentes ou desconhecidos.');
        }
        $clean = self::create(
            $record['id'],
            $record['empresa'],
            $record['cargo'],
            $record['data_envio'],
            $record['origem'],
            $record['salario_centavos'],
            $record['notas']
        );
        if (!is_string($record['etapa']) || !array_key_exists($record['etapa'], self::TRANSITIONS)) {
            throw new InvalidArgumentException('Etapa inválida.');
        }
        if (!is_array($record['historico']) || count($record['historico']) > 100) {
            throw new InvalidArgumentException('Histórico inválido.');
        }
        $previous = 'enviada';
        $previousDay = $record['data_envio'];
        foreach ($record['historico'] as $event) {
            if (!is_array($event) || array_keys($event) !== ['de', 'para', 'data']) {
                throw new InvalidArgumentException('Evento do histórico inválido.');
            }
            if ($event['de'] !== $previous ||
                !in_array($event['para'], self::TRANSITIONS[$previous], true)) {
                throw new InvalidArgumentException('Sequência de etapas inválida.');
            }
            $day = self::day($event['data']);
            if ($day < $previousDay) {
                throw new InvalidArgumentException('Histórico fora de ordem.');
            }
            $previous = $event['para'];
            $previousDay = $day;
        }
        if ($previous !== $record['etapa']) {
            throw new InvalidArgumentException('Etapa final não corresponde ao histórico.');
        }
        $clean['etapa'] = $record['etapa'];
        $clean['historico'] = array_values($record['historico']);
        return $clean;
    }

    /** @param list<array<string,mixed>> $items */
    public static function validateRegistry(array $items): array
    {
        if (count($items) > self::MAX_RECORDS) {
            throw new InvalidArgumentException('Limite de 5000 candidaturas excedido.');
        }
        $ids = [];
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Registro inválido.');
            }
            $clean = self::validate($item);
            if (isset($ids[$clean['id']])) {
                throw new InvalidArgumentException('ID duplicado.');
            }
            $ids[$clean['id']] = true;
            $out[] = $clean;
        }
        return $out;
    }

    public static function add(array $items, array $record): array
    {
        $registry = self::validateRegistry($items);
        if (count($registry) >= self::MAX_RECORDS) {
            throw new InvalidArgumentException('Limite de candidaturas atingido.');
        }
        return self::validateRegistry([...$registry, $record]);
    }

    public static function advance(
        array $record, string $next, string $day
    ): array {
        $record = self::validate($record);
        if (!in_array($next, self::TRANSITIONS[$record['etapa']], true)) {
            throw new InvalidArgumentException('Transição de etapa inválida.');
        }
        self::day($day);
        $history = $record['historico'];
        $last = $history === []
            ? $record['data_envio']
            : $history[count($history) - 1]['data'];
        if ($day < $last) {
            throw new InvalidArgumentException('Evento anterior ao último registro.');
        }
        if (count($history) >= 100) {
            throw new InvalidArgumentException('Limite de histórico atingido.');
        }
        $history[] = ['de' => $record['etapa'], 'para' => $next, 'data' => $day];
        $record['etapa'] = $next;
        $record['historico'] = $history;
        return $record;
    }

    public static function advanceById(
        array $items, string $id, string $next, string $day
    ): array {
        $registry = self::validateRegistry($items);
        $changed = false;
        foreach ($registry as &$record) {
            if ($record['id'] === $id) {
                $record = self::advance($record, $next, $day);
                $changed = true;
                break;
            }
        }
        unset($record);
        if (!$changed) {
            throw new InvalidArgumentException('Candidatura não encontrada.');
        }
        return $registry;
    }

    public static function remove(array $items, string $id): array
    {
        $registry = self::validateRegistry($items);
        $filtered = array_values(array_filter(
            $registry, static fn (array $item): bool => $item['id'] !== $id
        ));
        if (count($registry) === count($filtered)) {
            throw new InvalidArgumentException('Candidatura não encontrada.');
        }
        return $filtered;
    }

    public static function filter(
        array $items, string $status = 'all', string $query = ''
    ): array {
        if ($status !== 'all' && !array_key_exists($status, self::TRANSITIONS)) {
            throw new InvalidArgumentException('Filtro de etapa inválido.');
        }
        if (strlen($query) > 120) {
            throw new InvalidArgumentException('Consulta muito extensa.');
        }
        $search = strtolower(trim($query));
        $filtered = array_filter(
            self::validateRegistry($items),
            static function (array $item) use ($status, $search): bool {
                $match = $status === 'all' || $item['etapa'] === $status;
                $needle = strtolower(
                    $item['empresa'] . ' ' . $item['cargo'] . ' ' . $item['id']
                );
                return $match && ($search === '' || str_contains($needle, $search));
            }
        );
        usort($filtered, static fn (array $a, array $b): int =>
            [$b['data_envio'], $a['id']] <=> [$a['data_envio'], $b['id']]);
        return array_values($filtered);
    }

    public static function overdue(
        array $items, string $today, int $afterDays = 14
    ): array {
        self::day($today);
        if ($afterDays < 1 || $afterDays > 365) {
            throw new InvalidArgumentException('Janela precisa estar entre 1 e 365 dias.');
        }
        $closed = ['contratada', 'rejeitada', 'retirada'];
        $result = [];
        foreach (self::validateRegistry($items) as $record) {
            if (in_array($record['etapa'], $closed, true)) {
                continue;
            }
            $history = $record['historico'];
            $lastDay = $history === [] ? $record['data_envio']
                : $history[count($history) - 1]['data'];
            $days = (new DateTimeImmutable($lastDay))
                ->diff(new DateTimeImmutable($today))->days;
            if ($today >= $lastDay && $days >= $afterDays) {
                $result[] = $record;
            }
        }
        return $result;
    }

    public static function summary(array $items): array
    {
        $registry = self::validateRegistry($items);
        $counts = array_fill_keys(array_keys(self::TRANSITIONS), 0);
        foreach ($registry as $record) {
            $counts[$record['etapa']]++;
        }
        return [
            'total' => count($registry),
            'por_etapa' => $counts,
            'ativas' => $counts['enviada'] + $counts['triagem'] +
                $counts['entrevista'] + $counts['proposta'],
            'encerradas' => $counts['contratada'] + $counts['rejeitada'] +
                $counts['retirada'],
        ];
    }
}

// Keep the project dependency-free: grapheme limits use byte-safe fallbacks.
function mbLength(string $value): int
{
    return preg_match_all('/./us', $value) ?: 0;
}
