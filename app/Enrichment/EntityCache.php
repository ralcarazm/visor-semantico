<?php
declare(strict_types=1);

namespace KnowledgeMap\Enrichment;

final class EntityCache
{
    public function __construct(private readonly string $directory)
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
    }

    public function get(string $key): ?array
    {
        $path = $this->pathFor($key);
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    public function set(string $key, array $data): void
    {
        $path = $this->pathFor($key);
        $data['_cached_at'] = gmdate('c');
        file_put_contents(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    private function pathFor(string $key): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $key) ?: sha1($key);
        return rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safe . '.json';
    }
}
