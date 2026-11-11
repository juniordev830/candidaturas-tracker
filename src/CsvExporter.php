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
        // Some spreadsheet importers ignore Unicode spaces and format controls
        // (e.g. NBSP, zero-width space and BOM) before evaluating formulas.
        // Keep the original value visible but neutralize every risky prefix.
        return preg_match('/^[\s\p{Z}\p{Cf}]*[=+\-@]/u', $value) === 1
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
    /**
     * Exporta as prioridades de acompanhamento em ordem de urgência.
     * Reutiliza a validação do domínio; não envia mensagens nem grava arquivos.
     */
    public static function exportFollowUps(
        array $records, string $today, int $afterDays = 14, int $limit = 20
    ): string {
        $rows = Applications::followUps($records, $today, $afterDays, $limit);
        $headers = [
            'id', 'empresa', 'cargo', 'etapa',
            'ultima_atualizacao', 'dias_sem_atualizacao',
        ];
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new RuntimeException('Não foi possível abrir buffer CSV.');
        }
        try {
            fputcsv($stream, $headers, ',', '"', '');
            foreach ($rows as $item) {
                $cells = [];
                foreach ($headers as $field) {
                    $cells[] = self::safeCell((string) $item[$field]);
                }
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
