<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use KnowledgeMap\Enrichment\CommonsClient;
use KnowledgeMap\Enrichment\EntityCache;
use KnowledgeMap\Enrichment\WikidataClient;
use KnowledgeMap\Support\HttpClient;
use KnowledgeMap\Support\JsonResponse;
use KnowledgeMap\Support\UriHelper;

try {
    $uri = isset($_GET['uri']) && is_string($_GET['uri']) ? trim($_GET['uri']) : '';
    $id = isset($_GET['id']) && is_string($_GET['id']) ? strtoupper(trim($_GET['id'])) : '';

    if ($id === '' && $uri !== '') {
        $id = UriHelper::wikidataIdFromUri($uri) ?? '';
    }

    if ($id === '' || !preg_match('/^Q\d+$/', $id)) {
        JsonResponse::error('Indica una URI de Wikidata o un identificador Q válido.', 400, [
            'example_uri' => 'https://www.wikidata.org/entity/Q5586',
            'example_id' => 'Q5586',
        ]);
    }

    $timeout = (int) km_config('enrichment.timeout_seconds', 12);
    $userAgent = (string) km_config('wikidata.user_agent', 'KnowledgeMapPrototype/0.4');
    $httpClient = new HttpClient();

    $commonsClient = null;
    if ((bool) km_config('commons.enabled', true)) {
        $commonsClient = new CommonsClient(
            $httpClient,
            new EntityCache((string) km_config('paths.image_cache')),
            (string) km_config('commons.api_url'),
            $userAgent,
            (int) km_config('commons.thumbnail_width', 400),
            $timeout
        );
    }

    $wikidataClient = new WikidataClient(
        $httpClient,
        new EntityCache((string) km_config('paths.entity_cache')),
        $commonsClient,
        (string) km_config('wikidata.entity_data_base_url'),
        $userAgent,
        (array) km_config('languages.preferred', ['ca', 'es', 'en', '']),
        (string) km_config('wikidata.image_property', 'P18'),
        $timeout
    );

    $entity = $wikidataClient->getEntity($id);
    if ($entity === null) {
        JsonResponse::error('No se ha encontrado la entidad en Wikidata.', 404, ['id' => $id]);
    }

    JsonResponse::send([
        'status' => 'ok',
        'entity' => $entity,
    ]);
} catch (Throwable $exception) {
    JsonResponse::error('No se ha podido enriquecer la entidad.', 500, [
        'exception' => $exception->getMessage(),
    ]);
}
