<?php
declare(strict_types=1);

namespace KnowledgeMap\Support;

use RuntimeException;

final class HttpClient
{
    public function getJson(string $url, array $headers = [], int $timeoutSeconds = 10): array
    {
        $body = $this->get($url, $headers, $timeoutSeconds);
        $json = json_decode($body, true);

        if (!is_array($json)) {
            throw new RuntimeException('La respuesta no es JSON válido.');
        }

        return $json;
    }

    public function get(string $url, array $headers = [], int $timeoutSeconds = 10): string
    {
        if (function_exists('curl_init')) {
            return $this->getWithCurl($url, $headers, $timeoutSeconds);
        }

        return $this->getWithFileGetContents($url, $headers, $timeoutSeconds);
    }

    private function getWithCurl(string $url, array $headers, int $timeoutSeconds): string
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('No se ha podido inicializar cURL.');
        }

        $curlHeaders = [];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = $name . ': ' . $value;
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => min(6, $timeoutSeconds),
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($curl);
        $error = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($body === false || $body === '') {
            throw new RuntimeException('La petición HTTP ha fallado: ' . ($error !== '' ? $error : 'respuesta vacía'));
        }

        if ($statusCode >= 400) {
            throw new RuntimeException('La petición HTTP ha devuelto estado ' . $statusCode . '.');
        }

        return (string) $body;
    }

    private function getWithFileGetContents(string $url, array $headers, int $timeoutSeconds): string
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
                'header' => implode("\r\n", $headerLines),
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false || $body === '') {
            throw new RuntimeException('La petición HTTP ha fallado. Comprueba allow_url_fopen o activa cURL.');
        }

        return $body;
    }
}
