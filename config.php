<?php
declare(strict_types=1);

/**
 * Visor Semántico configuration.
 *
 * Extended exploratory profile with broader support for:
 * - Dublin Core Element Set 1.1 (dc)
 * - DCMI Metadata Terms (dcterms)
 * - FOAF
 * - Schema.org entities and properties commonly used for videogames,
 *   books, films, audiovisual works and visual/artistic works.
 */

$schemaLabelPredicates = [];
foreach (['name', 'headline'] as $term) {
    $schemaLabelPredicates[] = 'http://schema.org/' . $term;
    $schemaLabelPredicates[] = 'https://schema.org/' . $term;
}

$schemaDescriptionPredicates = [];
foreach (['description', 'disambiguatingDescription'] as $term) {
    $schemaDescriptionPredicates[] = 'http://schema.org/' . $term;
    $schemaDescriptionPredicates[] = 'https://schema.org/' . $term;
}

$schemaImagePredicates = [];
foreach (['image', 'photo', 'thumbnailUrl', 'contentUrl', 'associatedMedia', 'logo', 'screenshot'] as $term) {
    $schemaImagePredicates[] = 'http://schema.org/' . $term;
    $schemaImagePredicates[] = 'https://schema.org/' . $term;
}

$schemaHiddenPredicates = [];
foreach (['sameAs', 'url', 'mainEntityOfPage'] as $term) {
    $schemaHiddenPredicates[] = 'http://schema.org/' . $term;
    $schemaHiddenPredicates[] = 'https://schema.org/' . $term;
}

$schemaLiteralNodePredicates = [];
foreach ([
    // Common CreativeWork / Thing exploration facets.
    'genre',
    'keywords',
    'inLanguage',
    'encodingFormat',
    'learningResourceType',
    'educationalUse',
    'accessMode',
    'accessModeSufficient',
    'accessibilityAPI',
    'accessibilityControl',
    'accessibilityFeature',
    'accessibilityHazard',
    'accessibilitySummary',
    'contentRating',

    // Visual/artistic works.
    'material',
    'artform',
    'artMedium',
    'artworkSurface',
    'color',

    // Videogames and software.
    'gamePlatform',
    'gameEdition',
    'playMode',
    'numberOfPlayers',
    'operatingSystem',
    'applicationCategory',
    'applicationSubCategory',
    'applicationSuite',
    'softwareVersion',
    'softwareRequirements',
    'processorRequirements',
    'memoryRequirements',
    'storageRequirements',
    'permissions',
    'countriesSupported',
    'countriesNotSupported',

    // Books and publishing.
    'bookFormat',
    'bookEdition',
    'abridged',
    'pagination',

    // Film/audiovisual.
    'subtitleLanguage',
] as $term) {
    $schemaLiteralNodePredicates[] = 'http://schema.org/' . $term;
    $schemaLiteralNodePredicates[] = 'https://schema.org/' . $term;
}

$dcElementLabels = [
    'contributor' => 'contribuidor',
    'coverage' => 'cobertura',
    'creator' => 'creado por',
    'date' => 'fecha',
    'description' => 'descripción',
    'format' => 'formato',
    'identifier' => 'identificador',
    'language' => 'lengua',
    'publisher' => 'publicado por',
    'relation' => 'relacionado con',
    'rights' => 'derechos',
    'source' => 'fuente',
    'subject' => 'tema',
    'title' => 'título',
    'type' => 'tipo',
];

$dcElementPredicateLabels = [];
foreach ($dcElementLabels as $term => $label) {
    $dcElementPredicateLabels['http://purl.org/dc/elements/1.1/' . $term] = $label;
}

$dctermsLabels = [
    'abstract' => 'resumen',
    'accessRights' => 'derechos de acceso',
    'accrualMethod' => 'método de incorporación',
    'accrualPeriodicity' => 'periodicidad de incorporación',
    'accrualPolicy' => 'política de incorporación',
    'alternative' => 'título alternativo',
    'audience' => 'audiencia',
    'available' => 'fecha de disponibilidad',
    'bibliographicCitation' => 'cita bibliográfica',
    'conformsTo' => 'conforme a',
    'contributor' => 'contribuidor',
    'coverage' => 'cobertura',
    'created' => 'fecha de creación',
    'creator' => 'creado por',
    'date' => 'fecha',
    'dateAccepted' => 'fecha de aceptación',
    'dateCopyrighted' => 'fecha de copyright',
    'dateSubmitted' => 'fecha de envío',
    'description' => 'descripción',
    'educationLevel' => 'nivel educativo',
    'extent' => 'extensión',
    'format' => 'formato',
    'hasFormat' => 'tiene formato',
    'hasPart' => 'tiene parte',
    'hasVersion' => 'tiene versión',
    'identifier' => 'identificador',
    'instructionalMethod' => 'método didáctico',
    'isFormatOf' => 'es formato de',
    'isPartOf' => 'forma parte de',
    'isReferencedBy' => 'es referenciado por',
    'isReplacedBy' => 'es reemplazado por',
    'isRequiredBy' => 'es requerido por',
    'issued' => 'fecha de publicación',
    'isVersionOf' => 'es versión de',
    'language' => 'lengua',
    'license' => 'licencia',
    'mediator' => 'mediador',
    'medium' => 'material o soporte',
    'modified' => 'fecha de modificación',
    'provenance' => 'procedencia',
    'publisher' => 'publicado por',
    'references' => 'referencia',
    'relation' => 'relacionado con',
    'replaces' => 'reemplaza a',
    'requires' => 'requiere',
    'rights' => 'derechos',
    'rightsHolder' => 'titular de derechos',
    'source' => 'fuente',
    'spatial' => 'cobertura espacial',
    'subject' => 'tema',
    'tableOfContents' => 'sumario',
    'temporal' => 'cobertura temporal',
    'title' => 'título',
    'type' => 'tipo',
    'valid' => 'fecha de validez',
];

$dctermsPredicateLabels = [];
foreach ($dctermsLabels as $term => $label) {
    $dctermsPredicateLabels['http://purl.org/dc/terms/' . $term] = $label;
}

$foafPredicateLabels = [
    'http://xmlns.com/foaf/0.1/Agent' => 'agente',
    'http://xmlns.com/foaf/0.1/Person' => 'persona',
    'http://xmlns.com/foaf/0.1/Organization' => 'organización',
    'http://xmlns.com/foaf/0.1/Group' => 'grupo',
    'http://xmlns.com/foaf/0.1/Project' => 'proyecto',
    'http://xmlns.com/foaf/0.1/Document' => 'documento',
    'http://xmlns.com/foaf/0.1/Image' => 'imagen',
    'http://xmlns.com/foaf/0.1/OnlineAccount' => 'cuenta en línea',
    'http://xmlns.com/foaf/0.1/OnlineChatAccount' => 'cuenta de chat',
    'http://xmlns.com/foaf/0.1/OnlineEcommerceAccount' => 'cuenta de comercio electrónico',
    'http://xmlns.com/foaf/0.1/OnlineGamingAccount' => 'cuenta de juego en línea',

    'http://xmlns.com/foaf/0.1/name' => 'nombre',
    'http://xmlns.com/foaf/0.1/title' => 'tratamiento',
    'http://xmlns.com/foaf/0.1/givenName' => 'nombre de pila',
    'http://xmlns.com/foaf/0.1/familyName' => 'apellido',
    'http://xmlns.com/foaf/0.1/firstName' => 'nombre',
    'http://xmlns.com/foaf/0.1/lastName' => 'apellido',
    'http://xmlns.com/foaf/0.1/nick' => 'alias',
    'http://xmlns.com/foaf/0.1/depiction' => 'representación visual',
    'http://xmlns.com/foaf/0.1/depicts' => 'representa',
    'http://xmlns.com/foaf/0.1/img' => 'imagen',
    'http://xmlns.com/foaf/0.1/logo' => 'logotipo',
    'http://xmlns.com/foaf/0.1/homepage' => 'página principal',
    'http://xmlns.com/foaf/0.1/page' => 'página',
    'http://xmlns.com/foaf/0.1/weblog' => 'blog',
    'http://xmlns.com/foaf/0.1/workplaceHomepage' => 'sitio web del lugar de trabajo',
    'http://xmlns.com/foaf/0.1/schoolHomepage' => 'sitio web del centro educativo',
    'http://xmlns.com/foaf/0.1/workInfoHomepage' => 'página de información laboral',
    'http://xmlns.com/foaf/0.1/publications' => 'publicaciones',
    'http://xmlns.com/foaf/0.1/currentProject' => 'proyecto actual',
    'http://xmlns.com/foaf/0.1/pastProject' => 'proyecto anterior',
    'http://xmlns.com/foaf/0.1/made' => 'creó',
    'http://xmlns.com/foaf/0.1/maker' => 'creado por',
    'http://xmlns.com/foaf/0.1/knows' => 'conoce a',
    'http://xmlns.com/foaf/0.1/based_near' => 'ubicado cerca de',
    'http://xmlns.com/foaf/0.1/interest' => 'interés',
    'http://xmlns.com/foaf/0.1/topic_interest' => 'interés temático',
    'http://xmlns.com/foaf/0.1/topic' => 'tema',
    'http://xmlns.com/foaf/0.1/primaryTopic' => 'tema principal',
    'http://xmlns.com/foaf/0.1/isPrimaryTopicOf' => 'es tema principal de',
    'http://xmlns.com/foaf/0.1/member' => 'miembro',
    'http://xmlns.com/foaf/0.1/membershipClass' => 'clase de membresía',
    'http://xmlns.com/foaf/0.1/account' => 'cuenta',
    'http://xmlns.com/foaf/0.1/holdsAccount' => 'tiene cuenta',
    'http://xmlns.com/foaf/0.1/accountName' => 'nombre de cuenta',
    'http://xmlns.com/foaf/0.1/accountServiceHomepage' => 'servicio de la cuenta',
    'http://xmlns.com/foaf/0.1/mbox' => 'correo electrónico',
    'http://xmlns.com/foaf/0.1/mbox_sha1sum' => 'hash SHA-1 del correo',
    'http://xmlns.com/foaf/0.1/phone' => 'teléfono',
    'http://xmlns.com/foaf/0.1/gender' => 'género',
    'http://xmlns.com/foaf/0.1/age' => 'edad',
    'http://xmlns.com/foaf/0.1/birthday' => 'cumpleaños',
    'http://xmlns.com/foaf/0.1/status' => 'estado',
    'http://xmlns.com/foaf/0.1/jabberID' => 'ID de Jabber',
    'http://xmlns.com/foaf/0.1/aimChatID' => 'ID de AIM',
    'http://xmlns.com/foaf/0.1/icqChatID' => 'ID de ICQ',
    'http://xmlns.com/foaf/0.1/msnChatID' => 'ID de MSN',
    'http://xmlns.com/foaf/0.1/yahooChatID' => 'ID de Yahoo',
    'http://xmlns.com/foaf/0.1/skypeID' => 'ID de Skype',
    'http://xmlns.com/foaf/0.1/openid' => 'OpenID',
    'http://xmlns.com/foaf/0.1/tipjar' => 'donaciones',
    'http://xmlns.com/foaf/0.1/fundedBy' => 'financiado por',
    'http://xmlns.com/foaf/0.1/theme' => 'tema visual',
    'http://xmlns.com/foaf/0.1/plan' => 'plan',
];

$schemaPredicateLabelsBase = [
    // Generic Thing / CreativeWork.
    'name' => 'nombre',
    'alternateName' => 'nombre alternativo',
    'headline' => 'titular',
    'description' => 'descripción',
    'disambiguatingDescription' => 'descripción desambiguadora',
    'identifier' => 'identificador',
    'sameAs' => 'equivalente a',
    'url' => 'URL',
    'mainEntityOfPage' => 'entidad principal de la página',
    'image' => 'imagen',
    'photo' => 'fotografía',
    'thumbnailUrl' => 'miniatura',
    'contentUrl' => 'URL del contenido',
    'embedUrl' => 'URL de inserción',
    'associatedMedia' => 'medio asociado',
    'encoding' => 'codificación',
    'encodingFormat' => 'formato de codificación',
    'inLanguage' => 'lengua',
    'keywords' => 'palabras clave',
    'genre' => 'género',
    'about' => 'trata sobre',
    'mentions' => 'menciona',
    'abstract' => 'resumen',
    'isPartOf' => 'forma parte de',
    'hasPart' => 'tiene parte',
    'exampleOfWork' => 'ejemplo de obra',
    'workExample' => 'ejemplar de obra',
    'translationOfWork' => 'traducción de',
    'translator' => 'traducido por',
    'citation' => 'cita',
    'comment' => 'comentario',
    'commentCount' => 'número de comentarios',
    'review' => 'reseña',
    'aggregateRating' => 'valoración agregada',
    'contentRating' => 'clasificación por edades',
    'copyrightHolder' => 'titular de copyright',
    'copyrightNotice' => 'aviso de copyright',
    'copyrightYear' => 'año de copyright',
    'license' => 'licencia',
    'usageInfo' => 'información de uso',
    'acquireLicensePage' => 'página para adquirir licencia',
    'conditionsOfAccess' => 'condiciones de acceso',
    'accessibilityAPI' => 'API de accesibilidad',
    'accessibilityControl' => 'control de accesibilidad',
    'accessibilityFeature' => 'característica de accesibilidad',
    'accessibilityHazard' => 'riesgo de accesibilidad',
    'accessibilitySummary' => 'resumen de accesibilidad',
    'accessMode' => 'modo de acceso',
    'accessModeSufficient' => 'modo de acceso suficiente',
    'dateCreated' => 'fecha de creación',
    'datePublished' => 'fecha de publicación',
    'dateModified' => 'fecha de modificación',
    'temporalCoverage' => 'cobertura temporal',
    'spatialCoverage' => 'cobertura espacial',
    'contentLocation' => 'lugar representado',
    'locationCreated' => 'creado en',
    'countryOfOrigin' => 'país de origen',

    // People and organizations involved in works.
    'author' => 'autor',
    'creator' => 'creado por',
    'contributor' => 'contribuidor',
    'editor' => 'editor',
    'illustrator' => 'ilustrador',
    'artist' => 'artista',
    'publisher' => 'publicado por',
    'producer' => 'productor',
    'provider' => 'proveedor',
    'sponsor' => 'patrocinador',
    'funder' => 'financiador',
    'accountablePerson' => 'responsable',
    'maintainer' => 'mantenedor',

    // Visual artwork, museums, photographs and art objects.
    'artform' => 'forma artística',
    'artMedium' => 'técnica o medio',
    'artworkSurface' => 'soporte',
    'material' => 'material',
    'color' => 'color',
    'width' => 'anchura',
    'height' => 'altura',
    'depth' => 'profundidad',
    'size' => 'tamaño',
    'caption' => 'pie de imagen',
    'exifData' => 'datos EXIF',
    'representativeOfPage' => 'representativa de la página',

    // Books and publishing.
    'isbn' => 'ISBN',
    'issn' => 'ISSN',
    'bookFormat' => 'formato del libro',
    'bookEdition' => 'edición del libro',
    'abridged' => 'abreviado',
    'numberOfPages' => 'número de páginas',
    'pagination' => 'paginación',

    // Films and audiovisual works.
    'actor' => 'actor',
    'actors' => 'actores',
    'director' => 'director',
    'directors' => 'directores',
    'musicBy' => 'música de',
    'productionCompany' => 'productora',
    'trailer' => 'tráiler',
    'subtitleLanguage' => 'lengua de subtítulos',
    'duration' => 'duración',
    'video' => 'vídeo',
    'audio' => 'audio',
    'recordedAt' => 'grabado en',
    'releasedEvent' => 'evento de lanzamiento',

    // Videogames and software.
    'gamePlatform' => 'plataforma de juego',
    'gameServer' => 'servidor de juego',
    'gameTip' => 'consejo de juego',
    'cheatCode' => 'truco',
    'gameEdition' => 'edición del videojuego',
    'playMode' => 'modo de juego',
    'numberOfPlayers' => 'número de jugadores',
    'characterAttribute' => 'atributo del personaje',
    'quest' => 'misión',
    'gameItem' => 'objeto de juego',
    'gameLocation' => 'lugar de juego',
    'applicationCategory' => 'categoría de aplicación',
    'applicationSubCategory' => 'subcategoría de aplicación',
    'applicationSuite' => 'suite de aplicaciones',
    'softwareVersion' => 'versión del software',
    'softwareRequirements' => 'requisitos del software',
    'operatingSystem' => 'sistema operativo',
    'processorRequirements' => 'requisitos de procesador',
    'memoryRequirements' => 'requisitos de memoria',
    'storageRequirements' => 'requisitos de almacenamiento',
    'permissions' => 'permisos',
    'downloadUrl' => 'URL de descarga',
    'installUrl' => 'URL de instalación',
    'screenshot' => 'captura de pantalla',
    'releaseNotes' => 'notas de versión',
    'countriesSupported' => 'países soportados',
    'countriesNotSupported' => 'países no soportados',

    // Structured values.
    'value' => 'valor',
    'unitText' => 'unidad',
    'unitCode' => 'código de unidad',
    'minValue' => 'valor mínimo',
    'maxValue' => 'valor máximo',
    'termCode' => 'código del término',
];

$schemaPredicateLabels = [];
foreach ($schemaPredicateLabelsBase as $term => $label) {
    $schemaPredicateLabels['http://schema.org/' . $term] = $label;
    $schemaPredicateLabels['https://schema.org/' . $term] = $label;
}

$schemaTypeGroupsBase = [
    // Generic and creative works.
    'Thing' => 'entity',
    'CreativeWork' => 'work',
    'CreativeWorkSeries' => 'work',
    'Dataset' => 'work',
    'DigitalDocument' => 'work',
    'MediaObject' => 'media',
    'ImageObject' => 'image',
    'AudioObject' => 'media',
    'VideoObject' => 'media',
    'Clip' => 'media',

    // Visual/artistic works.
    'VisualArtwork' => 'work',
    'Painting' => 'work',
    'Photograph' => 'image',
    'Sculpture' => 'work',
    'Drawing' => 'work',
    'CoverArt' => 'image',

    // Books, comics and publications.
    'Book' => 'work',
    'BookSeries' => 'work',
    'Audiobook' => 'work',
    'Chapter' => 'work',
    'Article' => 'work',
    'ScholarlyArticle' => 'work',
    'NewsArticle' => 'work',
    'Report' => 'work',
    'Manuscript' => 'work',
    'Periodical' => 'work',
    'PublicationIssue' => 'work',
    'PublicationVolume' => 'work',
    'ComicStory' => 'work',
    'ComicSeries' => 'work',
    'ComicIssue' => 'work',
    'ComicCoverArt' => 'image',

    // Cinema, TV and audiovisual.
    'Movie' => 'work',
    'MovieSeries' => 'work',
    'TVSeries' => 'work',
    'TVSeason' => 'work',
    'TVEpisode' => 'work',
    'Episode' => 'work',
    'ScreeningEvent' => 'event',

    // Games, videogames, software and machines/products.
    'Game' => 'work',
    'VideoGame' => 'work',
    'VideoGameSeries' => 'work',
    'GameServer' => 'entity',
    'SoftwareApplication' => 'work',
    'MobileApplication' => 'work',
    'WebApplication' => 'work',
    'Product' => 'entity',
    'ProductModel' => 'entity',
    'IndividualProduct' => 'entity',

    // Agents, places and institutions.
    'Person' => 'person',
    'Organization' => 'organization',
    'Corporation' => 'organization',
    'EducationalOrganization' => 'organization',
    'PerformingGroup' => 'organization',
    'MusicGroup' => 'organization',
    'Place' => 'place',
    'Country' => 'place',
    'City' => 'place',
    'AdministrativeArea' => 'place',

    // Auxiliary entities.
    'DefinedTerm' => 'term',
    'DefinedTermSet' => 'term',
    'QuantitativeValue' => 'entity',
    'Duration' => 'entity',
    'Language' => 'term',
    'Audience' => 'entity',
    'Rating' => 'entity',
    'AggregateRating' => 'entity',
    'Review' => 'work',
    'Offer' => 'entity',
    'Event' => 'event',
];

$schemaTypeGroups = [];
foreach ($schemaTypeGroupsBase as $term => $group) {
    $schemaTypeGroups['http://schema.org/' . $term] = $group;
    $schemaTypeGroups['https://schema.org/' . $term] = $group;
}

$dcmitypeGroups = [
    'http://purl.org/dc/dcmitype/Collection' => 'collection',
    'http://purl.org/dc/dcmitype/Dataset' => 'work',
    'http://purl.org/dc/dcmitype/Event' => 'event',
    'http://purl.org/dc/dcmitype/Image' => 'image',
    'http://purl.org/dc/dcmitype/InteractiveResource' => 'work',
    'http://purl.org/dc/dcmitype/MovingImage' => 'media',
    'http://purl.org/dc/dcmitype/PhysicalObject' => 'entity',
    'http://purl.org/dc/dcmitype/Service' => 'entity',
    'http://purl.org/dc/dcmitype/Software' => 'work',
    'http://purl.org/dc/dcmitype/Sound' => 'media',
    'http://purl.org/dc/dcmitype/StillImage' => 'image',
    'http://purl.org/dc/dcmitype/Text' => 'work',
];

$dctermsClassGroups = [
    'http://purl.org/dc/terms/Agent' => 'person',
    'http://purl.org/dc/terms/AgentClass' => 'organization',
    'http://purl.org/dc/terms/BibliographicResource' => 'work',
    'http://purl.org/dc/terms/FileFormat' => 'term',
    'http://purl.org/dc/terms/Frequency' => 'term',
    'http://purl.org/dc/terms/Jurisdiction' => 'place',
    'http://purl.org/dc/terms/LicenseDocument' => 'work',
    'http://purl.org/dc/terms/LinguisticSystem' => 'term',
    'http://purl.org/dc/terms/Location' => 'place',
    'http://purl.org/dc/terms/MediaType' => 'term',
    'http://purl.org/dc/terms/MediaTypeOrExtent' => 'term',
    'http://purl.org/dc/terms/MethodOfAccrual' => 'term',
    'http://purl.org/dc/terms/MethodOfInstruction' => 'term',
    'http://purl.org/dc/terms/PeriodOfTime' => 'event',
    'http://purl.org/dc/terms/PhysicalMedium' => 'material',
    'http://purl.org/dc/terms/PhysicalResource' => 'entity',
    'http://purl.org/dc/terms/Policy' => 'work',
    'http://purl.org/dc/terms/ProvenanceStatement' => 'work',
    'http://purl.org/dc/terms/RightsStatement' => 'work',
    'http://purl.org/dc/terms/SizeOrDuration' => 'entity',
    'http://purl.org/dc/terms/Standard' => 'work',
];

$foafTypeGroups = [
    'http://xmlns.com/foaf/0.1/Agent' => 'person',
    'http://xmlns.com/foaf/0.1/Person' => 'person',
    'http://xmlns.com/foaf/0.1/Organization' => 'organization',
    'http://xmlns.com/foaf/0.1/Group' => 'organization',
    'http://xmlns.com/foaf/0.1/Project' => 'work',
    'http://xmlns.com/foaf/0.1/Document' => 'work',
    'http://xmlns.com/foaf/0.1/Image' => 'image',
    'http://xmlns.com/foaf/0.1/OnlineAccount' => 'entity',
    'http://xmlns.com/foaf/0.1/OnlineChatAccount' => 'entity',
    'http://xmlns.com/foaf/0.1/OnlineEcommerceAccount' => 'entity',
    'http://xmlns.com/foaf/0.1/OnlineGamingAccount' => 'entity',
];

return [
    'app' => [
        'name' => 'Visor Semántico',
        'version' => '1.0',
        'debug' => true,
    ],

    'paths' => [
        'storage' => __DIR__ . '/storage',
        'uploads' => __DIR__ . '/storage/uploads',
        'maps' => __DIR__ . '/storage/maps',
        'entity_cache' => __DIR__ . '/storage/cache/entities',
        'image_cache' => __DIR__ . '/storage/cache/images',
    ],

    'upload' => [
        'max_size_bytes' => 5 * 1024 * 1024,
        'allowed_extensions' => ['ttl', 'nt', 'n3', 'rdf', 'xml', 'jsonld', 'csv', 'tsv'],
    ],

    'languages' => [
        // Orden de preferencia para etiquetas y descripciones de Wikidata/RDF.
        'preferred' => ['es', 'ca', 'en', ''],
        'fallback' => 'en',
    ],

    'rdf' => [
        'label_predicates' => array_values(array_unique(array_merge([
            'http://www.w3.org/2000/01/rdf-schema#label',
            'http://www.w3.org/2004/02/skos/core#prefLabel',
            'http://www.w3.org/2004/02/skos/core#altLabel',
            'http://purl.org/dc/terms/title',
            'http://purl.org/dc/terms/alternative',
            'http://purl.org/dc/elements/1.1/title',
            'http://xmlns.com/foaf/0.1/name',
            'http://xmlns.com/foaf/0.1/nick',
        ], $schemaLabelPredicates))),

        'description_predicates' => array_values(array_unique(array_merge([
            'http://www.w3.org/2000/01/rdf-schema#comment',
            'http://www.w3.org/2004/02/skos/core#definition',
            'http://www.w3.org/2004/02/skos/core#scopeNote',
            'http://purl.org/dc/terms/description',
            'http://purl.org/dc/terms/abstract',
            'http://purl.org/dc/elements/1.1/description',
        ], $schemaDescriptionPredicates))),

        'image_predicates' => array_values(array_unique(array_merge([
            'http://xmlns.com/foaf/0.1/depiction',
            'http://xmlns.com/foaf/0.1/img',
            'http://xmlns.com/foaf/0.1/logo',
            'http://www.europeana.eu/schemas/edm/isShownBy',
            'http://www.europeana.eu/schemas/edm/object',
            'http://www.europeana.eu/schemas/edm/preview',
        ], $schemaImagePredicates))),

        'type_predicates' => [
            'http://www.w3.org/1999/02/22-rdf-syntax-ns#type',
        ],

        // Criterio exploratorio: se oculta solo ruido técnico, duplicación web
        // y datos personales/técnicos de FOAF que suelen ensuciar el grafo.
        'hidden_predicates' => array_values(array_unique(array_merge([
            'http://www.w3.org/1999/02/22-rdf-syntax-ns#type',
            'http://www.w3.org/2002/07/owl#sameAs',
            'http://www.w3.org/2000/01/rdf-schema#seeAlso',
            'http://xmlns.com/foaf/0.1/isPrimaryTopicOf',
            'http://xmlns.com/foaf/0.1/mbox',
            'http://xmlns.com/foaf/0.1/mbox_sha1sum',
            'http://xmlns.com/foaf/0.1/phone',
            'http://xmlns.com/foaf/0.1/dnaChecksum',
        ], $schemaHiddenPredicates))),

        // Literales que pueden convertirse en nodos visibles para exploración.
        // Se excluyen títulos, nombres, descripciones largas, identificadores y fechas.
        'literal_node_predicates' => array_values(array_unique(array_merge([
            // Dublin Core Element Set 1.1.
            'http://purl.org/dc/elements/1.1/coverage',
            'http://purl.org/dc/elements/1.1/format',
            'http://purl.org/dc/elements/1.1/language',
            'http://purl.org/dc/elements/1.1/rights',
            'http://purl.org/dc/elements/1.1/subject',
            'http://purl.org/dc/elements/1.1/type',

            // DCMI Metadata Terms.
            'http://purl.org/dc/terms/accessRights',
            'http://purl.org/dc/terms/accrualMethod',
            'http://purl.org/dc/terms/accrualPeriodicity',
            'http://purl.org/dc/terms/accrualPolicy',
            'http://purl.org/dc/terms/audience',
            'http://purl.org/dc/terms/coverage',
            'http://purl.org/dc/terms/educationLevel',
            'http://purl.org/dc/terms/extent',
            'http://purl.org/dc/terms/format',
            'http://purl.org/dc/terms/instructionalMethod',
            'http://purl.org/dc/terms/language',
            'http://purl.org/dc/terms/medium',
            'http://purl.org/dc/terms/rights',
            'http://purl.org/dc/terms/spatial',
            'http://purl.org/dc/terms/subject',
            'http://purl.org/dc/terms/temporal',
            'http://purl.org/dc/terms/type',

            // FOAF.
            'http://xmlns.com/foaf/0.1/interest',
            'http://xmlns.com/foaf/0.1/topic_interest',
            'http://xmlns.com/foaf/0.1/status',
        ], $schemaLiteralNodePredicates))),

        'predicate_labels' => array_merge(
            [
                'http://www.w3.org/2000/01/rdf-schema#label' => 'etiqueta',
                'http://www.w3.org/2000/01/rdf-schema#comment' => 'comentario',
                'http://www.w3.org/2000/01/rdf-schema#seeAlso' => 'véase también',
                'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' => 'tipo',
                'http://www.w3.org/2002/07/owl#sameAs' => 'equivalente a',
                'http://www.w3.org/2004/02/skos/core#prefLabel' => 'etiqueta preferente',
                'http://www.w3.org/2004/02/skos/core#altLabel' => 'etiqueta alternativa',
                'http://www.w3.org/2004/02/skos/core#definition' => 'definición',
                'http://www.w3.org/2004/02/skos/core#scopeNote' => 'nota de alcance',
            ],
            $dcElementPredicateLabels,
            $dctermsPredicateLabels,
            $foafPredicateLabels,
            $schemaPredicateLabels
        ),

        'type_groups' => array_merge(
            $schemaTypeGroups,
            $dcmitypeGroups,
            $dctermsClassGroups,
            $foafTypeGroups,
            [
                // Compatibilidad con ejemplos antiguos o vocabularios locales ya usados.
                'https://example.org/Material' => 'material',
                'https://example.org/resource/Material' => 'material',
            ]
        ),
    ],

    'enrichment' => [
        // El grafo intenta enriquecer URIs externas por defecto. Puedes desactivarlo con ?enrich=0.
        'enabled_by_default' => true,
        'timeout_seconds' => 12,
    ],

    'wikidata' => [
        'enabled' => true,
        // Cámbialo por una URL o email institucional si se publica la herramienta.
        'user_agent' => 'VisorSemantico/1.0 (local development; https://example.org/contact)',
        'entity_data_base_url' => 'https://www.wikidata.org/wiki/Special:EntityData/',
        'image_property' => 'P18',
    ],

    'commons' => [
        'enabled' => true,
        'api_url' => 'https://commons.wikimedia.org/w/api.php',
        'thumbnail_width' => 400,
    ],

    'debug' => [
        'max_triples_in_response' => 300,
    ],
];
