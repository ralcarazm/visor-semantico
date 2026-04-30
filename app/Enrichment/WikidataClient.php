<?php
declare(strict_types=1);

namespace KnowledgeMap\Enrichment;

use KnowledgeMap\Support\HttpClient;
use RuntimeException;

final class WikidataClient
{
    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly EntityCache $cache,
        private readonly ?CommonsClient $commonsClient,
        private readonly string $entityDataBaseUrl,
        private readonly string $userAgent,
        private readonly array $preferredLanguages,
        private readonly string $imageProperty = 'P18',
        private readonly int $timeoutSeconds = 10,
    ) {
    }

    public function getEntity(string $wikidataId): ?array
    {
        $wikidataId = strtoupper(trim($wikidataId));
        if (!preg_match('/^Q\d+$/', $wikidataId)) {
            throw new RuntimeException('Identificador de Wikidata no válido: ' . $wikidataId);
        }

        $cacheKey = 'wikidata_' . $wikidataId;
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            $cached['from_cache'] = true;
            return $cached;
        }

        $url = rtrim($this->entityDataBaseUrl, '/') . '/' . $wikidataId . '.json';
        $data = $this->httpClient->getJson($url, [
            'Accept' => 'application/json',
            'User-Agent' => $this->userAgent,
        ], $this->timeoutSeconds);

        $entity = $data['entities'][$wikidataId] ?? null;
        if (!is_array($entity) || ($entity['missing'] ?? false) === true) {
            return null;
        }

        $labels = is_array($entity['labels'] ?? null) ? $entity['labels'] : [];
        $descriptions = is_array($entity['descriptions'] ?? null) ? $entity['descriptions'] : [];
        $claims = is_array($entity['claims'] ?? null) ? $entity['claims'] : [];

        $imageFileName = $this->firstCommonsFileName($claims[$this->imageProperty] ?? []);
        $imageInfo = null;
        if ($imageFileName !== null && $this->commonsClient !== null) {
            try {
                $imageInfo = $this->commonsClient->getImageInfo($imageFileName);
            } catch (\Throwable $exception) {
                $imageInfo = [
                    'file_name' => $imageFileName,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        $instanceOf = $this->firstEntityId($claims['P31'] ?? []);

        $result = [
            'id' => $wikidataId,
            'uri' => 'https://www.wikidata.org/entity/' . $wikidataId,
            'label' => $this->selectLocalizedValue($labels),
            'description' => $this->selectLocalizedValue($descriptions),
            'image_file_name' => $imageFileName,
            'image' => is_array($imageInfo) ? (string) ($imageInfo['url'] ?? '') : '',
            'thumbnail' => is_array($imageInfo) ? (string) ($imageInfo['thumbnail'] ?? '') : '',
            'image_info' => $imageInfo,
            'instance_of' => $instanceOf,
            'source' => 'wikidata',
            'from_cache' => false,
        ];

        $this->cache->set($cacheKey, $result);

        return $result;
    }

    private function selectLocalizedValue(array $values): string
    {
        foreach ($this->preferredLanguages as $language) {
            if ($language === '') {
                continue;
            }

            $candidate = $values[$language]['value'] ?? null;
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        foreach ($values as $candidate) {
            $value = $candidate['value'] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function firstCommonsFileName(array $claims): ?string
    {
        foreach ($claims as $claim) {
            if (!is_array($claim)) {
                continue;
            }

            $value = $claim['mainsnak']['datavalue']['value'] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function firstEntityId(array $claims): ?string
    {
        foreach ($claims as $claim) {
            if (!is_array($claim)) {
                continue;
            }

            $value = $claim['mainsnak']['datavalue']['value'] ?? null;
            if (is_array($value)) {
                $id = $value['id'] ?? null;
                if (is_string($id) && preg_match('/^Q\d+$/', $id)) {
                    return $id;
                }
            }
        }

        return null;
    }
}
