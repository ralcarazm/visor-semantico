<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use KnowledgeMap\Enrichment\CommonsClient;
use KnowledgeMap\Enrichment\EntityCache;
use KnowledgeMap\Enrichment\GraphEnricher;
use KnowledgeMap\Enrichment\WikidataClient;
use KnowledgeMap\Rdf\GraphBuilder;
use KnowledgeMap\Rdf\TripleReader;
use KnowledgeMap\Support\HttpClient;
use KnowledgeMap\Support\JsonResponse;

try {
    $filePath = null;
    $source = 'sample';

    if (isset($_GET['file']) && is_string($_GET['file']) && $_GET['file'] !== '') {
        $fileId = basename($_GET['file']);
        $candidate = rtrim((string) km_config('paths.uploads'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileId;

        if (!is_file($candidate)) {
            JsonResponse::error('No se ha encontrado el fichero subido solicitado.', 404, [
                'file' => $fileId,
            ]);
        }

        $filePath = $candidate;
        $source = 'upload';
    } else {
        $sample = isset($_GET['sample']) && is_string($_GET['sample']) && $_GET['sample'] !== ''
            ? basename($_GET['sample'])
            : 'hokusai.ttl';

        $candidate = dirname(__DIR__) . '/samples/' . $sample;
        if (!is_file($candidate)) {
            JsonResponse::error('No se ha encontrado el fichero de ejemplo solicitado.', 404, [
                'sample' => $sample,
            ]);
        }

        $filePath = $candidate;
    }

    $reader = new TripleReader();
    $builder = new GraphBuilder();

    $triples = $reader->read($filePath);
    $graph = $builder->build($triples);
    $graph['metadata']['source'] = $source;
    $graph['metadata']['file_name'] = basename($filePath);
    $graph['metadata']['file_size_bytes'] = is_file($filePath) ? filesize($filePath) : null;
    $graph['metadata']['generated_at'] = date(DATE_ATOM);

    $enrichDefault = (bool) km_config('enrichment.enabled_by_default', true);
    $enrichRequested = isset($_GET['enrich'])
        ? !in_array(strtolower((string) $_GET['enrich']), ['0', 'false', 'no'], true)
        : $enrichDefault;

    if ($enrichRequested && (bool) km_config('wikidata.enabled', true)) {
        $timeout = (int) km_config('enrichment.timeout_seconds', 12);
        $userAgent = (string) km_config('wikidata.user_agent', 'KnowledgeMapPrototype/0.7');
        $httpClient = new HttpClient();
        $entityCache = new EntityCache((string) km_config('paths.entity_cache'));
        $imageCache = new EntityCache((string) km_config('paths.image_cache'));

        $commonsClient = null;
        if ((bool) km_config('commons.enabled', true)) {
            $commonsClient = new CommonsClient(
                $httpClient,
                $imageCache,
                (string) km_config('commons.api_url'),
                $userAgent,
                (int) km_config('commons.thumbnail_width', 400),
                $timeout
            );
        }

        $wikidataClient = new WikidataClient(
            $httpClient,
            $entityCache,
            $commonsClient,
            (string) km_config('wikidata.entity_data_base_url'),
            $userAgent,
            (array) km_config('languages.preferred', ['ca', 'es', 'en', '']),
            (string) km_config('wikidata.image_property', 'P18'),
            $timeout
        );

        $enricher = new GraphEnricher($wikidataClient);
        $graph = $enricher->enrich($graph);
    } else {
        $graph['metadata']['enrichment'] = [
            'enabled' => false,
            'wikidata_nodes_detected' => 0,
            'wikidata_nodes_enriched' => 0,
            'from_cache' => 0,
            'errors' => [],
        ];
    }

    $includeTriples = isset($_GET['include_triples'])
        && !in_array(strtolower((string) $_GET['include_triples']), ['0', 'false', 'no'], true);

    if ($includeTriples) {
        $maxTriples = max(1, (int) km_config('debug.max_triples_in_response', 300));
        $graph['triples'] = array_slice($triples, 0, $maxTriples);
        $graph['metadata']['triples_included'] = count($graph['triples']);
        $graph['metadata']['triples_truncated'] = count($triples) > $maxTriples;
    }

    JsonResponse::send($graph);
} catch (Throwable $exception) {
    JsonResponse::error('No se ha podido generar el grafo.', 500, [
        'exception' => $exception->getMessage(),
    ]);
}
