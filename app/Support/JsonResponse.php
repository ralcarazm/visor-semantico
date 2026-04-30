<?php
declare(strict_types=1);

namespace KnowledgeMap\Support;

final class JsonResponse
{
    public static function send(array $data, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');

        echo json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        exit;
    }

    public static function error(string $message, int $statusCode = 400, array $extra = []): never
    {
        self::send([
            'status' => 'error',
            'message' => $message,
            'extra' => $extra,
        ], $statusCode);
    }
}
