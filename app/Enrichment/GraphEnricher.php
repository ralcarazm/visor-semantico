<?php
declare(strict_types=1);

namespace KnowledgeMap\Enrichment;

use KnowledgeMap\Support\UriHelper;

final class GraphEnricher
{
    public function __construct(private readonly WikidataClient $wikidataClient)
    {
    }

    /**
     * @param array{nodes:array<int,array<string,mixed>>, edges:array<int,array<string,mixed>>, metadata:array<string,mixed>} $graph
     * @return array{nodes:array<int,array<string,mixed>>, edges:array<int,array<string,mixed>>, metadata:array<string,mixed>}
     */
    public function enrich(array $graph): array
    {
        $stats = [
            'enabled' => true,
            'wikidata_nodes_detected' => 0,
            'wikidata_nodes_enriched' => 0,
            'from_cache' => 0,
            'warnings' => [],
            'errors' => [],
        ];

        foreach ($graph['nodes'] as &$node) {
            $uri = (string) ($node['uri'] ?? $node['id'] ?? '');
            $wikidataId = UriHelper::wikidataIdFromUri($uri);
            if ($wikidataId === null) {
                continue;
            }

            $stats['wikidata_nodes_detected']++;

            try {
                $entity = $this->wikidataClient->getEntity($wikidataId);
                if ($entity === null) {
                    continue;
                }

                $warning = $this->validationWarning($node, $entity);
                if ($warning !== null) {
                    $node['validation_warning'] = $warning['message'];
                    $stats['warnings'][] = $warning;
                }

                $this->mergeWikidataEntity($node, $entity, $warning === null);
                $stats['wikidata_nodes_enriched']++;

                if (($entity['from_cache'] ?? false) === true) {
                    $stats['from_cache']++;
                }
            } catch (\Throwable $exception) {
                $stats['errors'][] = [
                    'uri' => $uri,
                    'message' => $exception->getMessage(),
                ];
            }
        }
        unset($node);

        $graph['metadata']['enrichment'] = $stats;

        return $graph;
    }

    /**
     * @param array<string,mixed> $node
     * @param array<string,mixed> $entity
     * @return array<string,string>|null
     */
    private function validationWarning(array $node, array $entity): ?array
    {
        $typeLabel = strtolower((string) ($node['type_label'] ?? ''));
        $instanceOf = (string) ($entity['instance_of'] ?? '');
        $uri = (string) ($node['uri'] ?? $node['id'] ?? '');
        $id = (string) ($entity['id'] ?? '');
        $remoteLabel = (string) ($entity['label'] ?? '');

        if (($typeLabel === 'person' || $typeLabel === 'human') && $instanceOf !== '' && $instanceOf !== 'Q5') {
            return [
                'uri' => $uri,
                'wikidata_id' => $id,
                'remote_label' => $remoteLabel,
                'instance_of' => $instanceOf,
                'message' => 'La tripleta local declara una persona, pero Wikidata no identifica la entidad como ser humano. Revisa el QID.',
            ];
        }

        return null;
    }

    /**
     * @param array<string,mixed> $node
     * @param array<string,mixed> $entity
     */
    private function mergeWikidataEntity(array &$node, array $entity, bool $trustedEntity): void
    {
        $currentLabel = (string) ($node['label'] ?? '');
        $id = (string) ($entity['id'] ?? '');
        $externalLabel = (string) ($entity['label'] ?? '');

        if ($externalLabel !== '' && ($currentLabel === '' || $currentLabel === $id || str_starts_with($currentLabel, 'Q'))) {
            $node['label'] = $externalLabel;
        }

        if ($trustedEntity && !empty($entity['description']) && empty($node['description'])) {
            $node['description'] = (string) $entity['description'];
        }

        $thumbnail = (string) ($entity['thumbnail'] ?? '');
        $image = (string) ($entity['image'] ?? '');

        if ($trustedEntity && $thumbnail !== '') {
            $node['image'] = $thumbnail;
            $node['full_image'] = $image !== '' ? $image : $thumbnail;
            $node['shape'] = 'circularImage';
            $node['group'] = $node['group'] ?? 'external';
        }

        $node['wikidata_id'] = $id;
        $node['wikidata_url'] = 'https://www.wikidata.org/wiki/' . $id;
        $node['wikidata_label'] = $externalLabel;
        $node['wikidata_instance_of'] = (string) ($entity['instance_of'] ?? '');
        $node['enriched_from'] = 'wikidata';

        if ($trustedEntity && !empty($entity['image_info']) && is_array($entity['image_info'])) {
            $node['image_credit'] = [
                'file_name' => (string) ($entity['image_info']['file_name'] ?? ''),
                'author' => (string) ($entity['image_info']['author'] ?? ''),
                'licence' => (string) ($entity['image_info']['licence'] ?? ''),
                'licence_url' => (string) ($entity['image_info']['licence_url'] ?? ''),
                'description_url' => (string) ($entity['image_info']['description_url'] ?? ''),
            ];
        }

        $node['title'] = $this->buildNodeTitle($node);
    }

    /**
     * @param array<string,mixed> $node
     */
    private function buildNodeTitle(array $node): string
    {
        $lines = [];
        $lines[] = '<strong>' . htmlspecialchars((string) ($node['label'] ?? $node['id']), ENT_QUOTES, 'UTF-8') . '</strong>';

        if (!empty($node['validation_warning'])) {
            $lines[] = 'Aviso: ' . htmlspecialchars((string) $node['validation_warning'], ENT_QUOTES, 'UTF-8');
        }

        if (!empty($node['description'])) {
            $lines[] = htmlspecialchars((string) $node['description'], ENT_QUOTES, 'UTF-8');
        }

        if (!empty($node['wikidata_id'])) {
            $lines[] = 'Wikidata: ' . htmlspecialchars((string) $node['wikidata_id'], ENT_QUOTES, 'UTF-8');
        }

        if (!empty($node['wikidata_label']) && (string) $node['wikidata_label'] !== (string) ($node['label'] ?? '')) {
            $lines[] = 'Etiqueta Wikidata: ' . htmlspecialchars((string) $node['wikidata_label'], ENT_QUOTES, 'UTF-8');
        }

        if (!empty($node['image_credit']) && is_array($node['image_credit'])) {
            $credit = $node['image_credit'];
            if (!empty($credit['file_name'])) {
                $lines[] = 'Imagen: ' . htmlspecialchars((string) $credit['file_name'], ENT_QUOTES, 'UTF-8');
            }
            if (!empty($credit['licence'])) {
                $lines[] = 'Licencia: ' . htmlspecialchars((string) $credit['licence'], ENT_QUOTES, 'UTF-8');
            }
        }

        if (!empty($node['uri']) && is_string($node['uri']) && str_starts_with($node['uri'], 'http')) {
            $lines[] = '<small>' . htmlspecialchars($node['uri'], ENT_QUOTES, 'UTF-8') . '</small>';
        }

        return implode('<br>', $lines);
    }
}
