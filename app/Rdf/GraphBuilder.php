<?php
declare(strict_types=1);

namespace KnowledgeMap\Rdf;

use KnowledgeMap\Support\UriHelper;

final class GraphBuilder
{
    /**
     * Convierte tripletas normalizadas en nodos y aristas para vis-network.
     *
     * @param array<int, array{subject:string, predicate:string, object:string, object_type:string, lang?:string, datatype?:string}> $triples
     * @return array{nodes:array<int,array<string,mixed>>, edges:array<int,array<string,mixed>>, metadata:array<string,mixed>}
     */
    public function build(array $triples): array
    {
        $nodes = [];
        $edges = [];
        $labels = [];
        $descriptions = [];
        $properties = [];

        $labelPredicates = km_config('rdf.label_predicates', []);
        $descriptionPredicates = km_config('rdf.description_predicates', []);
        $imagePredicates = km_config('rdf.image_predicates', []);
        $typePredicates = km_config('rdf.type_predicates', []);
        $hiddenPredicates = km_config('rdf.hidden_predicates', []);
        $literalNodePredicates = km_config('rdf.literal_node_predicates', []);
        $predicateLabels = km_config('rdf.predicate_labels', []);
        $typeGroups = km_config('rdf.type_groups', []);
        $preferredLanguages = km_config('languages.preferred', ['ca', 'es', 'en', '']);

        foreach ($triples as $triple) {
            $subject = trim((string) $triple['subject']);
            $predicate = trim((string) $triple['predicate']);
            $object = trim((string) $triple['object']);
            $objectType = (string) ($triple['object_type'] ?? 'literal');
            $lang = (string) ($triple['lang'] ?? '');

            if ($subject === '' || $predicate === '' || $object === '') {
                continue;
            }

            $this->ensureNode($nodes, $subject);

            if (in_array($predicate, $labelPredicates, true) && $objectType === 'literal') {
                $labels[$subject][] = [
                    'value' => $object,
                    'lang' => $lang,
                ];
                continue;
            }

            if (in_array($predicate, $descriptionPredicates, true) && $objectType === 'literal') {
                $descriptions[$subject][] = [
                    'value' => $object,
                    'lang' => $lang,
                ];
                continue;
            }

            if (in_array($predicate, $imagePredicates, true)) {
                $nodes[$subject]['image'] = $object;
                $nodes[$subject]['shape'] = 'image';
                $nodes[$subject]['group'] = $nodes[$subject]['group'] ?? 'image';
                continue;
            }

            if (in_array($predicate, $typePredicates, true)) {
                $nodes[$subject]['type_uri'] = $object;
                $nodes[$subject]['type_label'] = UriHelper::shortLabel($object);
                $nodes[$subject]['group'] = $typeGroups[$object] ?? $this->inferGroupFromType($object);
                continue;
            }

            if ($objectType === 'uri') {
                if (in_array($predicate, $hiddenPredicates, true)) {
                    continue;
                }

                $this->ensureNode($nodes, $object);

                $edges[] = [
                        'id' => sha1($subject . '|' . $predicate . '|' . $object),
                        'from' => $subject,
                        'to' => $object,
                        'label' => UriHelper::predicateLabel($predicate, $predicateLabels),
                        'title' => htmlspecialchars($predicate, ENT_QUOTES, 'UTF-8'),
                        'arrows' => 'to',
                    ];


                continue;
            }

            if (in_array($predicate, $literalNodePredicates, true)) {
                $literalNodeId = UriHelper::literalNodeId($predicate, $object);
                $nodes[$literalNodeId] ??= [
                    'id' => $literalNodeId,
                    'label' => $object,
                    'title' => htmlspecialchars($object, ENT_QUOTES, 'UTF-8'),
                    'group' => $this->inferGroupFromPredicate($predicate),
                    'shape' => 'box',
                    'value' => 1,
                ];

                $edges[] = [
                    'id' => sha1($subject . '|' . $predicate . '|' . $literalNodeId),
                    'from' => $subject,
                    'to' => $literalNodeId,
                    'label' => UriHelper::predicateLabel($predicate, $predicateLabels),
                    'title' => htmlspecialchars($predicate, ENT_QUOTES, 'UTF-8'),
                    'arrows' => 'to',
                ];

                continue;
            }

            $properties[$subject][] = [
                'predicate' => UriHelper::predicateLabel($predicate, $predicateLabels),
                'predicate_uri' => $predicate,
                'value' => $object,
                'lang' => $lang,
            ];
        }

        foreach ($nodes as $id => &$node) {
            $label = $this->selectLanguageValue($labels[$id] ?? [], $preferredLanguages);
            if ($label !== null) {
                $node['label'] = $label;
            }

            $description = $this->selectLanguageValue($descriptions[$id] ?? [], $preferredLanguages);
            if ($description !== null) {
                $node['description'] = $description;
            }

            if (!isset($node['group'])) {
                $node['group'] = $this->inferGroupFromUri($id);
            }

            if (!isset($node['shape'])) {
                $node['shape'] = $node['group'] === 'work' ? 'box' : 'dot';
            }

            $node['title'] = $this->buildNodeTitle($node, $properties[$id] ?? []);
        }
        unset($node);

        return [
            'status' => 'ok',
            'nodes' => array_values($nodes),
            'edges' => array_values($edges),
            'metadata' => [
                'triples_count' => count($triples),
                'nodes_count' => count($nodes),
                'edges_count' => count($edges),
            ],
        ];
    }

    /**
     * @param array<string, array<string,mixed>> $nodes
     */
    private function ensureNode(array &$nodes, string $id): void
    {
        if (isset($nodes[$id])) {
            return;
        }

        $nodes[$id] = [
            'id' => $id,
            'label' => UriHelper::shortLabel($id),
            'uri' => $id,
        ];
    }

    /**
     * @param array<int, array{value:string, lang:string}> $candidates
     * @param array<int, string> $preferredLanguages
     */
    private function selectLanguageValue(array $candidates, array $preferredLanguages): ?string
    {
        if ($candidates === []) {
            return null;
        }

        foreach ($preferredLanguages as $preferred) {
            foreach ($candidates as $candidate) {
                if (($candidate['lang'] ?? '') === $preferred) {
                    return $candidate['value'];
                }
            }
        }

        return $candidates[0]['value'];
    }

    private function inferGroupFromUri(string $uri): string
    {
        if (str_contains($uri, 'wikidata.org/entity/')) {
            return 'external';
        }

        if (UriHelper::looksLikeImageUrl($uri)) {
            return 'image';
        }

        return 'entity';
    }

    private function inferGroupFromType(string $typeUri): string
    {
        $lower = strtolower($typeUri);

        if (str_contains($lower, 'person')) {
            return 'person';
        }

        if (str_contains($lower, 'creativework') || str_contains($lower, 'work')) {
            return 'work';
        }

        if (str_contains($lower, 'image')) {
            return 'image';
        }

        if (str_contains($lower, 'material')) {
            return 'material';
        }

        return 'entity';
    }

    private function inferGroupFromPredicate(string $predicate): string
    {
        $lower = strtolower($predicate);

        if (str_contains($lower, 'material') || str_contains($lower, 'medium')) {
            return 'material';
        }

        return 'literal';
    }

    /**
     * @param array<string,mixed> $node
     * @param array<int, array{predicate:string, predicate_uri:string, value:string, lang:string}> $properties
     */
    private function buildNodeTitle(array $node, array $properties): string
    {
        $lines = [];
        $lines[] = '<strong>' . htmlspecialchars((string) ($node['label'] ?? $node['id']), ENT_QUOTES, 'UTF-8') . '</strong>';

        if (!empty($node['type_label'])) {
            $lines[] = 'Tipo: ' . htmlspecialchars((string) $node['type_label'], ENT_QUOTES, 'UTF-8');
        }

        if (!empty($node['description'])) {
            $lines[] = htmlspecialchars((string) $node['description'], ENT_QUOTES, 'UTF-8');
        }

        $shown = 0;
        foreach ($properties as $property) {
            if ($shown >= 6) {
                $lines[] = '…';
                break;
            }

            $lines[] = htmlspecialchars($property['predicate'], ENT_QUOTES, 'UTF-8')
                . ': '
                . htmlspecialchars($property['value'], ENT_QUOTES, 'UTF-8');
            $shown++;
        }

        if (!empty($node['uri']) && is_string($node['uri']) && str_starts_with($node['uri'], 'http')) {
            $lines[] = '<small>' . htmlspecialchars($node['uri'], ENT_QUOTES, 'UTF-8') . '</small>';
        }

        return implode('<br>', $lines);
    }
}
