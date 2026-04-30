<?php
declare(strict_types=1);

namespace KnowledgeMap\Enrichment;

use KnowledgeMap\Support\HttpClient;
use RuntimeException;

final class CommonsClient
{
    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly EntityCache $cache,
        private readonly string $apiUrl,
        private readonly string $userAgent,
        private readonly int $thumbnailWidth = 400,
        private readonly int $timeoutSeconds = 10,
    ) {
    }

    public function getImageInfo(string $fileName): ?array
    {
        $fileName = $this->normaliseFileName($fileName);
        if ($fileName === '') {
            return null;
        }

        $cacheKey = 'commons_' . sha1($fileName . '_' . $this->thumbnailWidth);
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            $cached['from_cache'] = true;
            return $cached;
        }

        $query = http_build_query([
            'action' => 'query',
            'format' => 'json',
            'formatversion' => '2',
            'prop' => 'imageinfo',
            'titles' => 'File:' . $fileName,
            'iiprop' => 'url|extmetadata',
            'iiurlwidth' => $this->thumbnailWidth,
            'origin' => '*',
        ], '', '&', PHP_QUERY_RFC3986);

        $url = $this->apiUrl . '?' . $query;
        $data = $this->httpClient->getJson($url, [
            'Accept' => 'application/json',
            'User-Agent' => $this->userAgent,
        ], $this->timeoutSeconds);

        $pages = $data['query']['pages'] ?? [];
        if (!is_array($pages) || $pages === []) {
            return null;
        }

        $page = $pages[0] ?? null;
        if (!is_array($page) || isset($page['missing'])) {
            return null;
        }

        $imageInfo = $page['imageinfo'][0] ?? null;
        if (!is_array($imageInfo)) {
            return null;
        }

        $ext = $imageInfo['extmetadata'] ?? [];
        $result = [
            'file_name' => $fileName,
            'url' => (string) ($imageInfo['url'] ?? ''),
            'description_url' => (string) ($imageInfo['descriptionurl'] ?? ''),
            'thumbnail' => (string) ($imageInfo['thumburl'] ?? ($imageInfo['url'] ?? '')),
            'thumbnail_width' => $imageInfo['thumbwidth'] ?? null,
            'thumbnail_height' => $imageInfo['thumbheight'] ?? null,
            'mime' => (string) ($imageInfo['mime'] ?? ''),
            'author' => $this->metadataValue($ext, 'Artist'),
            'licence' => $this->metadataValue($ext, 'LicenseShortName'),
            'licence_url' => $this->metadataValue($ext, 'LicenseUrl'),
            'source' => 'wikimedia-commons',
            'from_cache' => false,
        ];

        $this->cache->set($cacheKey, $result);

        return $result;
    }

    private function normaliseFileName(string $fileName): string
    {
        $fileName = trim($fileName);
        $fileName = preg_replace('~^https?://commons\.wikimedia\.org/wiki/Special:FilePath/~i', '', $fileName) ?? $fileName;
        $fileName = preg_replace('~^https?://commons\.wikimedia\.org/wiki/File:~i', '', $fileName) ?? $fileName;
        $fileName = preg_replace('~^File:~i', '', $fileName) ?? $fileName;
        $fileName = rawurldecode($fileName);
        $fileName = str_replace('_', ' ', $fileName);

        return trim($fileName);
    }

    private function metadataValue(array $metadata, string $key): string
    {
        $value = $metadata[$key]['value'] ?? '';
        if (!is_string($value)) {
            return '';
        }

        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/', ' ', $value) ?: $value;

        return trim($value);
    }
}
