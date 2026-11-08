<?php
declare(strict_types=1);

namespace GX\Applications;

require_once __DIR__ . '/Applications.php';

use RuntimeException;

final class CsvExporter
{
    private const HEADINGS = [
        'id', 'empresa', 'cargo', 'origem', 'etapa', 'data_envio',
        'salario_centavos', 'quantidade_eventos',
    ];

    private static function safeCell(string $value): string
    {
        // Spreadsheet importers can execute formulas beginning with =,+,-,@.
        // Prefix those values with an apostrophe, including after leading spaces.
        return preg_match('/^\s*[=+\-@]/', $value) === 1
            ? "'" . $value : $value;
    }

    public static function export(array $records): string
    {
        $registry = Applications::validateRegistry($records);
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new RuntimeException('Não foi possível abrir buffer CSV.');
        }
        try {
            fputcsv($stream, self::HEADINGS, ',', '"', '');
            foreach ($registry as $record) {
                $row = [
                    $record['id'],
                    $record['empresa'],
                    $record['cargo'],
                    $record['origem'],
                    $record['etapa'],
                    $record['data_envio'],
                    $record['salario_centavos'] === null
                        ? '' : (string) $record['salario_centavos'],
                    (string) count($record['historico']),
                ];
                $cells = array_map(
                    static fn ($cell): string => self::safeCell((string) $cell),
                    $row
                );
                fputcsv($stream, $cells, ',', '"', '');
            }
            rewind($stream);
            $csv = stream_get_contents($stream);
            if ($csv === false) {
                throw new RuntimeException('Erro ao ler buffer CSV.');
            }
            return $csv;
        } finally {
            fclose($stream);
        }
    }
}
