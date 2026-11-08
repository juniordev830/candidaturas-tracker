<?php
declare(strict_types=1);

namespace GX\Applications;

require_once __DIR__ . '/Applications.php';

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class Storage
{
    private const VERSION = 1;
    private const MAX_BYTES = 1_000_000;

    public static function defaultPath(): string
    {
        $custom = getenv('GX_APPLICATIONS_FILE');
        if (is_string($custom) && $custom !== '') {
            return $custom;
        }
        $home = getenv('HOME') ?: sys_get_temp_dir();
        return rtrim($home, DIRECTORY_SEPARATOR) .
            DIRECTORY_SEPARATOR . '.gx-candidaturas' .
            DIRECTORY_SEPARATOR . 'candidaturas.json';
    }

    public static function load(?string $filename = null): array
    {
        $path = $filename ?? self::defaultPath();
        if (!file_exists($path)) {
            return [];
        }
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Arquivo não pode ser lido.');
        }
        clearstatcache(true, $path);
        $size = filesize($path);
        if ($size === false || $size > self::MAX_BYTES) {
            throw new RuntimeException('Arquivo excede 1 MB.');
        }
        $body = file_get_contents($path);
        if ($body === false) {
            throw new RuntimeException('Erro de leitura.');
        }
        try {
            $document = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('JSON inválido.', 0, $error);
        }
        if (!is_array($document) ||
            ($document['version'] ?? null) !== self::VERSION ||
            !isset($document['applications']) ||
            !is_array($document['applications'])) {
            throw new InvalidArgumentException('Formato ou versão do catálogo inválida.');
        }
        return Applications::validateRegistry($document['applications']);
    }

    public static function save(array $records, ?string $filename = null): int
    {
        $registry = Applications::validateRegistry($records);
        $path = $filename ?? self::defaultPath();
        $payload = [
            'version' => self::VERSION,
            'applications' => $registry,
        ];
        $body = json_encode(
            $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . "\n";
        if (strlen($body) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Catálogo excede 1 MB.');
        }
        $directory = dirname($path);
        if (!is_dir($directory) &&
            !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Falha ao criar diretório.');
        }
        $temp = tempnam($directory, '.gx-app-');
        if ($temp === false) {
            throw new RuntimeException('Não foi possível criar arquivo temporário.');
        }
        try {
            chmod($temp, 0600);
            $written = file_put_contents($temp, $body, LOCK_EX);
            if ($written !== strlen($body)) {
                throw new RuntimeException('Falha ao gravar documento.');
            }
            if (!rename($temp, $path)) {
                throw new RuntimeException('Falha ao substituir documento.');
            }
        } finally {
            if (file_exists($temp)) {
                unlink($temp);
            }
        }
        return count($registry);
    }
}
