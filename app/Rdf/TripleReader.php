<?php
declare(strict_types=1);

namespace KnowledgeMap\Rdf;

use RuntimeException;

final class TripleReader
{
    /**
     * Lee un fichero de tripletas y devuelve una representación normalizada.
     *
     * Soporte sin dependencias externas:
     * - CSV/TSV con columnas subject,predicate,object[,object_type,lang,datatype]
     * - N-Triples básico
     * - Turtle básico con @prefix, ;, ,, literales con idioma y rdf:type mediante "a"
     * - RDF/XML básico mediante SimpleXML o fallback de texto
     * - JSON-LD básico mediante json_decode()
     *
     * Si EasyRDF está instalado mediante Composer, se usa preferentemente para
     * Turtle, N-Triples, RDF/XML y JSON-LD.
     *
     * @return array<int, array{subject:string, predicate:string, object:string, object_type:string, lang?:string, datatype?:string}>
     */
    public function read(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new RuntimeException('El fichero no existe: ' . $filePath);
        }

        if (!is_readable($filePath)) {
            throw new RuntimeException('El fichero no se puede leer: ' . $filePath);
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($extension, ['csv', 'tsv'], true)) {
            return $this->readDelimited($filePath, $extension === 'tsv' ? "\t" : ',');
        }

        if (class_exists('EasyRdf\\Graph')) {
            try {
                return $this->readWithEasyRdf($filePath, $extension);
            } catch (\Throwable $exception) {
                // Si EasyRDF falla, probamos los parsers integrados para formatos simples.
            }
        }

        $data = file_get_contents($filePath);
        if ($data === false) {
            throw new RuntimeException('No se ha podido leer el contenido del fichero.');
        }

        if ($extension === 'nt') {
            return $this->readNTriples($data);
        }

        if (in_array($extension, ['ttl', 'n3'], true)) {
            return $this->readTurtleSubset($data);
        }

        if (in_array($extension, ['rdf', 'xml'], true)) {
            return $this->readRdfXmlSubset($data);
        }

        if ($extension === 'jsonld') {
            return $this->readJsonLdSubset($data);
        }

        throw new RuntimeException('Formato no soportado. Usa .ttl, .nt, .n3, .rdf, .xml, .jsonld, .csv o .tsv.');
    }

    /**
     * @return array<int, array{subject:string, predicate:string, object:string, object_type:string, lang?:string, datatype?:string}>
     */
    private function readWithEasyRdf(string $filePath, string $extension): array
    {
        $format = match ($extension) {
            'ttl', 'n3' => 'turtle',
            'nt' => 'ntriples',
            'rdf', 'xml' => 'rdfxml',
            'jsonld' => 'jsonld',
            default => 'guess',
        };

        $data = file_get_contents($filePath);
        if ($data === false) {
            throw new RuntimeException('No se ha podido leer el contenido del fichero.');
        }

        /** @var object $graph */
        $graph = new \EasyRdf\Graph();
        $graph->parse($data, $format);

        /** @var array<string, array<string, array<int, array<string, string>>>> $rdfPhp */
        $rdfPhp = $graph->toRdfPhp();
        $triples = [];

        foreach ($rdfPhp as $subject => $predicates) {
            foreach ($predicates as $predicate => $objects) {
                foreach ($objects as $object) {
                    $type = ($object['type'] ?? '') === 'uri' ? 'uri' : 'literal';
                    $triple = [
                        'subject' => (string) $subject,
                        'predicate' => (string) $predicate,
                        'object' => (string) ($object['value'] ?? ''),
                        'object_type' => $type,
                    ];

                    if (isset($object['lang'])) {
                        $triple['lang'] = (string) $object['lang'];
                    }

                    if (isset($object['datatype'])) {
                        $triple['datatype'] = (string) $object['datatype'];
                    }

                    $triples[] = $triple;
                }
            }
        }

        return $triples;
    }

    /**
     * @return array<int, array{subject:string, predicate:string, object:string, object_type:string, lang?:string, datatype?:string}>
     */
    private function readDelimited(string $filePath, string $delimiter): array
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('No se ha podido abrir el fichero delimitado.');
        }

        $triples = [];
        $headers = null;
        $lineNumber = 0;

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $lineNumber++;
            $row = array_map(static fn ($value): string => trim((string) $value), $row);

            if ($row === [] || implode('', $row) === '') {
                continue;
            }

            if ($lineNumber === 1) {
                $lower = array_map(static fn ($value): string => strtolower($value), $row);
                if (in_array('subject', $lower, true) && in_array('predicate', $lower, true) && in_array('object', $lower, true)) {
                    $headers = $lower;
                    continue;
                }
            }

            if ($headers !== null) {
                $assoc = [];
                foreach ($headers as $index => $header) {
                    $assoc[$header] = $row[$index] ?? '';
                }

                $subject = $assoc['subject'] ?? '';
                $predicate = $assoc['predicate'] ?? '';
                $object = $assoc['object'] ?? '';
                $objectType = $assoc['object_type'] ?? $assoc['type'] ?? '';
                $lang = $assoc['lang'] ?? '';
                $datatype = $assoc['datatype'] ?? '';
            } else {
                $subject = $row[0] ?? '';
                $predicate = $row[1] ?? '';
                $object = $row[2] ?? '';
                $objectType = $row[3] ?? '';
                $lang = $row[4] ?? '';
                $datatype = $row[5] ?? '';
            }

            if ($subject === '' || $predicate === '' || $object === '') {
                continue;
            }

            if ($objectType === '') {
                $objectType = preg_match('/^https?:\/\//i', $object) ? 'uri' : 'literal';
            }

            $triple = [
                'subject' => $subject,
                'predicate' => $predicate,
                'object' => $object,
                'object_type' => $objectType === 'uri' ? 'uri' : 'literal',
            ];

            if ($lang !== '') {
                $triple['lang'] = $lang;
            }

            if ($datatype !== '') {
                $triple['datatype'] = $datatype;
            }

            $triples[] = $triple;
        }

        fclose($handle);

        return $triples;
    }

    /**
     * @return array<int, array{subject:string, predicate:string, object:string, object_type:string, lang?:string, datatype?:string}>
     */
    private function readNTriples(string $data): array
    {
        $triples = [];
        $lines = preg_split('/\R/u', $data) ?: [];

        foreach ($lines as $line) {
            $line = trim($this->stripComment($line));
            if ($line === '') {
                continue;
            }

            $pattern = '/^<([^>]*)>\s+<([^>]*)>\s+(<[^>]*>|"(?:\\\\.|[^"\\\\])*"(?:@[a-zA-Z0-9-]+|\^\^<[^>]+>)?)\s*\.\s*$/u';
            if (!preg_match($pattern, $line, $matches)) {
                continue;
            }

            $subject = $matches[1];
            $predicate = $matches[2];
            $objectToken = $matches[3];
            $object = $this->parseObjectToken($objectToken, []);

            if ($object === null) {
                continue;
            }

            $triples[] = [
                'subject' => $subject,
                'predicate' => $predicate,
                'object' => $object['value'],
                'object_type' => $object['type'],
            ] + $object['extra'];
        }

        return $triples;
    }

    /**
     * Parser mínimo de Turtle suficiente para prototipos y ejemplos sencillos.
     * No pretende sustituir a EasyRDF para Turtle completo.
     *
     * @return array<int, array{subject:string, predicate:string, object:string, object_type:string, lang?:string, datatype?:string}>
     */
    private function readTurtleSubset(string $data): array
    {
        $prefixes = $this->defaultPrefixes();

        if (preg_match_all('/@prefix\s+([A-Za-z][A-Za-z0-9_-]*):\s*<([^>]+)>\s*\./u', $data, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $prefixes[$match[1]] = $match[2];
            }
        }

        $data = preg_replace('/@prefix\s+[A-Za-z][A-Za-z0-9_-]*:\s*<[^>]+>\s*\./u', '', $data) ?? $data;
        $tokens = $this->tokenizeTurtle($data);
        $triples = [];
        $count = count($tokens);
        $i = 0;

        while ($i < $count) {
            if (($tokens[$i] ?? null) === '.') {
                $i++;
                continue;
            }

            $subjectToken = $tokens[$i++] ?? null;
            if ($subjectToken === null) {
                break;
            }

            $subject = $this->expandResourceToken($subjectToken, $prefixes, false);
            if ($subject === null) {
                $this->skipUntilDot($tokens, $i);
                continue;
            }

            while ($i < $count) {
                $token = $tokens[$i] ?? null;

                if ($token === '.') {
                    $i++;
                    break;
                }

                if ($token === ';') {
                    $i++;
                    continue;
                }

                $predicateToken = $tokens[$i++] ?? null;
                if ($predicateToken === null || $predicateToken === '.') {
                    break;
                }

                $predicate = $this->expandPredicateToken($predicateToken, $prefixes);
                if ($predicate === null) {
                    $this->skipUntilPredicateBoundary($tokens, $i);
                    continue;
                }

                while ($i < $count) {
                    $objectToken = $tokens[$i++] ?? null;
                    if ($objectToken === null || in_array($objectToken, [';', ',', '.'], true)) {
                        if ($objectToken === '.') {
                            break 2;
                        }
                        break;
                    }

                    $object = $this->parseObjectToken($objectToken, $prefixes);
                    if ($object !== null) {
                        $triples[] = [
                            'subject' => $subject,
                            'predicate' => $predicate,
                            'object' => $object['value'],
                            'object_type' => $object['type'],
                        ] + $object['extra'];
                    }

                    $next = $tokens[$i] ?? null;
                    if ($next === ',') {
                        $i++;
                        continue;
                    }

                    if ($next === ';') {
                        $i++;
                        break;
                    }

                    if ($next === '.') {
                        $i++;
                        break 2;
                    }

                    break;
                }
            }
        }

        return $triples;
    }

    /**
     * Parser RDF/XML básico para despliegues sin Composer.
     *
     * @return array<int, array{subject:string, predicate:string, object:string, object_type:string, lang?:string, datatype?:string}>
     */
    private function readRdfXmlSubset(string $data): array
    {
        if (!class_exists('SimpleXMLElement')) {
            return $this->readRdfXmlRegexSubset($data);
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($data);
        libxml_use_internal_errors($previous);

        if (!$xml instanceof \SimpleXMLElement) {
            throw new RuntimeException('No se ha podido parsear el RDF/XML.');
        }

        $namespaces = $xml->getDocNamespaces(true);
        $triples = [];
        $rdfNs = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';

        $rootNodes = [];
        $namespaceUris = array_values(array_unique(array_filter(array_values($namespaces))));

        foreach ($namespaceUris as $uri) {
            foreach ($xml->children($uri) as $node) {
                $rootNodes[] = $node;
            }
        }

        if ($rootNodes === []) {
            foreach ($xml->children() as $node) {
                $rootNodes[] = $node;
            }
        }

        foreach ($rootNodes as $node) {
            $this->rdfXmlNodeToTriples($node, $namespaces, $triples, $rdfNs);
        }

        return $triples;
    }

    /**
     * @param array<string, string> $namespaces
     * @param array<int, array<string, string>> $triples
     */
    private function rdfXmlNodeToTriples(\SimpleXMLElement $node, array $namespaces, array &$triples, string $rdfNs): void
    {
        $subject = $this->rdfXmlSubject($node, $rdfNs);
        if ($subject === null) {
            return;
        }

        $nodeName = $node->getName();
        $nodeNs = $this->simpleXmlNamespaceUri($node);
        if ($nodeName !== 'Description' && $nodeNs !== $rdfNs) {
            $triples[] = [
                'subject' => $subject,
                'predicate' => $rdfNs . 'type',
                'object' => $nodeNs . $nodeName,
                'object_type' => 'uri',
            ];
        }

        foreach ($namespaces as $prefix => $uri) {
            foreach ($node->children($uri) as $child) {
                $predicate = $uri . $child->getName();
                $attributes = $child->attributes($rdfNs);
                $resource = (string) ($attributes['resource'] ?? '');
                $nodeId = (string) ($attributes['nodeID'] ?? '');
                $datatype = (string) ($attributes['datatype'] ?? '');

                if ($resource !== '') {
                    $triples[] = [
                        'subject' => $subject,
                        'predicate' => $predicate,
                        'object' => $resource,
                        'object_type' => 'uri',
                    ];
                    continue;
                }

                if ($nodeId !== '') {
                    $triples[] = [
                        'subject' => $subject,
                        'predicate' => $predicate,
                        'object' => '_:' . $nodeId,
                        'object_type' => 'uri',
                    ];
                    continue;
                }

                $literal = trim((string) $child);
                if ($literal === '') {
                    $nestedSubject = $this->rdfXmlSubject($child, $rdfNs);
                    if ($nestedSubject !== null) {
                        $triples[] = [
                            'subject' => $subject,
                            'predicate' => $predicate,
                            'object' => $nestedSubject,
                            'object_type' => 'uri',
                        ];
                    }
                    continue;
                }

                $triple = [
                    'subject' => $subject,
                    'predicate' => $predicate,
                    'object' => $literal,
                    'object_type' => 'literal',
                ];

                $xmlAttributes = $child->attributes('http://www.w3.org/XML/1998/namespace');
                $lang = (string) ($xmlAttributes['lang'] ?? '');
                if ($lang !== '') {
                    $triple['lang'] = $lang;
                }
                if ($datatype !== '') {
                    $triple['datatype'] = $datatype;
                }

                $triples[] = $triple;
            }
        }
    }

    private function rdfXmlSubject(\SimpleXMLElement $node, string $rdfNs): ?string
    {
        $attributes = $node->attributes($rdfNs);
        $about = (string) ($attributes['about'] ?? '');
        if ($about !== '') {
            return $about;
        }

        $id = (string) ($attributes['ID'] ?? '');
        if ($id !== '') {
            return '#' . $id;
        }

        $nodeId = (string) ($attributes['nodeID'] ?? '');
        if ($nodeId !== '') {
            return '_:' . $nodeId;
        }

        return null;
    }

    private function simpleXmlNamespaceUri(\SimpleXMLElement $node): string
    {
        if (function_exists('dom_import_simplexml')) {
            $dom = dom_import_simplexml($node);
            if ($dom instanceof \DOMElement && $dom->namespaceURI !== null) {
                return $dom->namespaceURI;
            }
        }

        return '';
    }

    /**
     * Parser JSON-LD básico para objetos con @context, @id, @type y @graph.
     *
     * @return array<int, array{subject:string, predicate:string, object:string, object_type:string, lang?:string, datatype?:string}>
     */
    private function readJsonLdSubset(string $data): array
    {
        $decoded = json_decode($data, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('No se ha podido parsear el JSON-LD.');
        }

        $context = $this->jsonLdContext($decoded['@context'] ?? []);
        $items = [];

        if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
            $items = $decoded['@graph'];
        } elseif ($this->isList($decoded)) {
            $items = $decoded;
        } else {
            $items = [$decoded];
        }

        $triples = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemContext = $context;
            if (isset($item['@context'])) {
                $itemContext = array_replace($itemContext, $this->jsonLdContext($item['@context']));
            }

            $subject = $this->jsonLdExpand((string) ($item['@id'] ?? $item['id'] ?? ''), $itemContext);
            if ($subject === '') {
                continue;
            }

            foreach ($item as $key => $value) {
                if ($key === '@context' || $key === '@id' || $key === 'id') {
                    continue;
                }

                if ($key === '@type' || $key === 'type') {
                    foreach ($this->asArray($value) as $typeValue) {
                        $type = $this->jsonLdExpand((string) $typeValue, $itemContext);
                        if ($type !== '') {
                            $triples[] = [
                                'subject' => $subject,
                                'predicate' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type',
                                'object' => $type,
                                'object_type' => 'uri',
                            ];
                        }
                    }
                    continue;
                }

                $predicate = $this->jsonLdExpand($key, $itemContext);
                if ($predicate === '') {
                    continue;
                }

                foreach ($this->asArray($value) as $objectValue) {
                    foreach ($this->jsonLdValueToObjects($objectValue, $itemContext) as $object) {
                        $triples[] = [
                            'subject' => $subject,
                            'predicate' => $predicate,
                            'object' => $object['value'],
                            'object_type' => $object['type'],
                        ] + $object['extra'];
                    }
                }
            }
        }

        return $triples;
    }

    /**
     * Fallback RDF/XML muy básico para entornos PHP sin SimpleXML.
     * Admite nodos del tipo <schema:CreativeWork rdf:about="..."> y propiedades
     * con literales o rdf:resource. Para RDF/XML completo conviene EasyRDF.
     *
     * @return array<int, array{subject:string, predicate:string, object:string, object_type:string, lang?:string, datatype?:string}>
     */
    private function readRdfXmlRegexSubset(string $data): array
    {
        $namespaces = $this->rdfXmlNamespacesFromString($data);
        $rdfNs = $namespaces['rdf'] ?? 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
        $triples = [];

        if (!preg_match_all('/<([A-Za-z_][A-Za-z0-9_.-]*):([A-Za-z_][A-Za-z0-9_.-]*)([^>]*)>(.*?)<\/\\1:\\2>/su', $data, $nodes, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($nodes as $node) {
            $prefix = $node[1];
            $localName = $node[2];
            $attributes = $node[3];
            $inner = $node[4];

            if ($prefix === 'rdf' && $localName === 'RDF') {
                if (preg_match_all('/<([A-Za-z_][A-Za-z0-9_.-]*):([A-Za-z_][A-Za-z0-9_.-]*)([^>]*)>(.*?)<\/\\1:\\2>/su', $inner, $innerNodes, PREG_SET_ORDER)) {
                    foreach ($innerNodes as $innerNode) {
                        $this->rdfXmlRegexNodeToTriples($innerNode, $namespaces, $rdfNs, $triples);
                    }
                }
                continue;
            }

            $this->rdfXmlRegexNodeToTriples($node, $namespaces, $rdfNs, $triples);
        }

        return $triples;
    }

    /** @return array<string, string> */
    private function rdfXmlNamespacesFromString(string $data): array
    {
        $namespaces = [];
        if (preg_match_all('/xmlns:([A-Za-z_][A-Za-z0-9_.-]*)="([^"]+)"/u', $data, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $namespaces[$match[1]] = html_entity_decode($match[2], ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }
        return $namespaces + $this->defaultPrefixes();
    }

    /**
     * @param array<int, string> $node
     * @param array<string, string> $namespaces
     * @param array<int, array<string, string>> $triples
     */
    private function rdfXmlRegexNodeToTriples(array $node, array $namespaces, string $rdfNs, array &$triples): void
    {
        $prefix = $node[1];
        $localName = $node[2];
        $attributes = $node[3];
        $inner = $node[4];
        $about = $this->rdfXmlRegexAttribute($attributes, 'rdf:about');
        $id = $this->rdfXmlRegexAttribute($attributes, 'rdf:ID');
        $nodeId = $this->rdfXmlRegexAttribute($attributes, 'rdf:nodeID');

        if ($about !== '') {
            $subject = $about;
        } elseif ($id !== '') {
            $subject = str_starts_with($id, '#') ? $id : '#' . $id;
        } elseif ($nodeId !== '') {
            $subject = '_:' . $nodeId;
        } else {
            return;
        }

        if (!($prefix === 'rdf' && $localName === 'Description')) {
            $triples[] = [
                'subject' => $subject,
                'predicate' => $rdfNs . 'type',
                'object' => ($namespaces[$prefix] ?? '') . $localName,
                'object_type' => 'uri',
            ];
        }

        if (preg_match_all('/<([A-Za-z_][A-Za-z0-9_.-]*):([A-Za-z_][A-Za-z0-9_.-]*)([^>]*)\/>/su', $inner, $selfClosingProps, PREG_SET_ORDER)) {
            foreach ($selfClosingProps as $prop) {
                $pPrefix = $prop[1];
                $pLocal = $prop[2];
                $pAttributes = $prop[3];
                $predicate = ($namespaces[$pPrefix] ?? '') . $pLocal;
                $resource = $this->rdfXmlRegexAttribute($pAttributes, 'rdf:resource');
                $nodeId = $this->rdfXmlRegexAttribute($pAttributes, 'rdf:nodeID');

                if ($resource !== '') {
                    $triples[] = [
                        'subject' => $subject,
                        'predicate' => $predicate,
                        'object' => $resource,
                        'object_type' => 'uri',
                    ];
                    continue;
                }

                if ($nodeId !== '') {
                    $triples[] = [
                        'subject' => $subject,
                        'predicate' => $predicate,
                        'object' => '_:' . $nodeId,
                        'object_type' => 'uri',
                    ];
                }
            }
        }

        $literalInner = preg_replace('/<([A-Za-z_][A-Za-z0-9_.-]*):([A-Za-z_][A-Za-z0-9_.-]*)([^>]*)\/>/su', '', $inner) ?? $inner;
        if (preg_match_all('/<([A-Za-z_][A-Za-z0-9_.-]*):([A-Za-z_][A-Za-z0-9_.-]*)([^>]*)>(.*?)<\/\\1:\\2>/su', $literalInner, $literalProps, PREG_SET_ORDER)) {
            foreach ($literalProps as $prop) {
                $pPrefix = $prop[1];
                $pLocal = $prop[2];
                $pAttributes = $prop[3];
                $rawValue = $prop[4] ?? '';
                $predicate = ($namespaces[$pPrefix] ?? '') . $pLocal;
                $literal = trim(strip_tags(html_entity_decode($rawValue, ENT_QUOTES | ENT_XML1, 'UTF-8')));

                if ($literal === '') {
                    continue;
                }

                $triple = [
                    'subject' => $subject,
                    'predicate' => $predicate,
                    'object' => $literal,
                    'object_type' => 'literal',
                ];

                $lang = $this->rdfXmlRegexAttribute($pAttributes, 'xml:lang');
                $datatype = $this->rdfXmlRegexAttribute($pAttributes, 'rdf:datatype');
                if ($lang !== '') {
                    $triple['lang'] = $lang;
                }
                if ($datatype !== '') {
                    $triple['datatype'] = $datatype;
                }

                $triples[] = $triple;
            }
        }
    }

    private function rdfXmlRegexAttribute(string $attributes, string $name): string
    {
        $pattern = '/\\s' . preg_quote($name, '/') . '="([^"]*)"/u';
        if (preg_match($pattern, $attributes, $match)) {
            return html_entity_decode($match[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        return '';
    }

    /** @return array<string, string> */
    private function defaultPrefixes(): array
    {
        return [
            'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            'skos' => 'http://www.w3.org/2004/02/skos/core#',
            'dcterms' => 'http://purl.org/dc/terms/',
            'dc' => 'http://purl.org/dc/elements/1.1/',
            'schema' => 'https://schema.org/',
            'foaf' => 'http://xmlns.com/foaf/0.1/',
            'edm' => 'http://www.europeana.eu/schemas/edm/',
            'owl' => 'http://www.w3.org/2002/07/owl#',
            'xsd' => 'http://www.w3.org/2001/XMLSchema#',
            'wd' => 'https://www.wikidata.org/entity/',
            'wdt' => 'https://www.wikidata.org/prop/direct/',
            'p' => 'https://www.wikidata.org/prop/',
            'ps' => 'https://www.wikidata.org/prop/statement/',
        ];
    }

    /** @return array<int, string> */
    private function tokenizeTurtle(string $data): array
    {
        $tokens = [];
        $length = strlen($data);
        $i = 0;

        while ($i < $length) {
            $char = $data[$i];

            if (ctype_space($char)) {
                $i++;
                continue;
            }

            if ($char === '#') {
                while ($i < $length && !in_array($data[$i], ["\n", "\r"], true)) {
                    $i++;
                }
                continue;
            }

            if (in_array($char, [';', ',', '.'], true)) {
                $tokens[] = $char;
                $i++;
                continue;
            }

            if ($char === '<') {
                $end = strpos($data, '>', $i + 1);
                if ($end === false) {
                    break;
                }
                $tokens[] = substr($data, $i, $end - $i + 1);
                $i = $end + 1;
                continue;
            }

            if ($char === '"') {
                $start = $i;
                $i++;
                $escaped = false;
                while ($i < $length) {
                    $current = $data[$i];
                    if ($escaped) {
                        $escaped = false;
                        $i++;
                        continue;
                    }
                    if ($current === '\\') {
                        $escaped = true;
                        $i++;
                        continue;
                    }
                    if ($current === '"') {
                        $i++;
                        break;
                    }
                    $i++;
                }

                if ($i < $length && $data[$i] === '@') {
                    $i++;
                    while ($i < $length && preg_match('/[A-Za-z0-9-]/', $data[$i])) {
                        $i++;
                    }
                } elseif (($i + 1) < $length && substr($data, $i, 2) === '^^') {
                    $i += 2;
                    if ($i < $length && $data[$i] === '<') {
                        $end = strpos($data, '>', $i + 1);
                        if ($end === false) {
                            break;
                        }
                        $i = $end + 1;
                    } else {
                        while ($i < $length && !ctype_space($data[$i]) && !in_array($data[$i], [';', ',', '.'], true)) {
                            $i++;
                        }
                    }
                }

                $tokens[] = substr($data, $start, $i - $start);
                continue;
            }

            $start = $i;
            while ($i < $length && !ctype_space($data[$i]) && !in_array($data[$i], [';', ',', '.'], true)) {
                $i++;
            }
            $tokens[] = substr($data, $start, $i - $start);
        }

        return $tokens;
    }

    private function expandPredicateToken(string $token, array $prefixes): ?string
    {
        if ($token === 'a') {
            return 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';
        }

        return $this->expandResourceToken($token, $prefixes, false);
    }

    private function expandResourceToken(string $token, array $prefixes, bool $allowLiteral): ?string
    {
        $token = trim($token);

        if ($token === '') {
            return null;
        }

        if (str_starts_with($token, '<') && str_ends_with($token, '>')) {
            return substr($token, 1, -1);
        }

        if (str_starts_with($token, '_:')) {
            return $token;
        }

        if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*):(.+)$/u', $token, $matches)) {
            $prefix = $matches[1];
            $local = $matches[2];
            if (isset($prefixes[$prefix])) {
                return $prefixes[$prefix] . $local;
            }
        }

        if (preg_match('/^https?:\/\//i', $token)) {
            return $token;
        }

        return $allowLiteral ? $token : null;
    }

    /** @return array{value:string, type:string, extra:array<string, string>}|null */
    private function parseObjectToken(string $token, array $prefixes): ?array
    {
        $token = trim($token);

        if ($token === '') {
            return null;
        }

        if (str_starts_with($token, '"')) {
            $pattern = '/^"((?:\\\\.|[^"\\\\])*)"(?:@([A-Za-z0-9-]+)|\^\^(.+))?$/su';
            if (!preg_match($pattern, $token, $matches)) {
                return null;
            }

            $value = stripcslashes($matches[1]);
            $extra = [];

            if (!empty($matches[2])) {
                $extra['lang'] = $matches[2];
            }

            if (!empty($matches[3])) {
                $datatype = $this->expandResourceToken($matches[3], $prefixes, false) ?? $matches[3];
                $extra['datatype'] = $datatype;
            }

            return [
                'value' => $value,
                'type' => 'literal',
                'extra' => $extra,
            ];
        }

        $resource = $this->expandResourceToken($token, $prefixes, true);
        if ($resource === null) {
            return null;
        }

        return [
            'value' => $resource,
            'type' => preg_match('/^https?:\/\//i', $resource) || str_starts_with($resource, '_:') ? 'uri' : 'literal',
            'extra' => [],
        ];
    }

    /** @return array<string, string> */
    private function jsonLdContext(mixed $context): array
    {
        $prefixes = $this->defaultPrefixes();

        if (!is_array($context)) {
            return $prefixes;
        }

        foreach ($context as $key => $value) {
            if ($key === '@vocab' && is_string($value)) {
                $prefixes[''] = $value;
                continue;
            }
            if (is_string($key) && is_string($value)) {
                $prefixes[$key] = $this->jsonLdExpandContextValue($value, $prefixes);
            }
            if (is_string($key) && is_array($value) && isset($value['@id']) && is_string($value['@id'])) {
                $prefixes[$key] = $this->jsonLdExpandContextValue($value['@id'], $prefixes);
            }
        }

        return $prefixes;
    }

    private function jsonLdExpandContextValue(string $value, array $context): string
    {
        if (preg_match('/^https?:\/\//i', $value)) {
            return $value;
        }

        if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*):(.+)$/u', $value, $matches)) {
            $prefix = $matches[1];
            $local = $matches[2];
            if (isset($context[$prefix])) {
                return $context[$prefix] . $local;
            }
        }

        return $value;
    }

    private function jsonLdExpand(string $value, array $context): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, '@')) {
            return $value;
        }

        if (preg_match('/^https?:\/\//i', $value)) {
            return $value;
        }

        if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*):(.+)$/u', $value, $matches)) {
            $prefix = $matches[1];
            $local = $matches[2];
            if (isset($context[$prefix])) {
                return $context[$prefix] . $local;
            }
        }

        if (isset($context[$value])) {
            return $context[$value];
        }

        if (isset($context[''])) {
            return $context[''] . $value;
        }

        return $value;
    }

    /**
     * @return array<int, array{value:string, type:string, extra:array<string, string>}>
     */
    private function jsonLdValueToObjects(mixed $value, array $context): array
    {
        if (is_array($value)) {
            if (isset($value['@id']) || isset($value['id'])) {
                $id = $this->jsonLdExpand((string) ($value['@id'] ?? $value['id']), $context);
                return $id === '' ? [] : [[
                    'value' => $id,
                    'type' => 'uri',
                    'extra' => [],
                ]];
            }

            if (isset($value['@value']) || isset($value['value'])) {
                $literal = (string) ($value['@value'] ?? $value['value']);
                $extra = [];
                if (isset($value['@language'])) {
                    $extra['lang'] = (string) $value['@language'];
                }
                if (isset($value['@type'])) {
                    $extra['datatype'] = $this->jsonLdExpand((string) $value['@type'], $context);
                }
                return [[
                    'value' => $literal,
                    'type' => 'literal',
                    'extra' => $extra,
                ]];
            }

            return [];
        }

        if (is_bool($value)) {
            return [[
                'value' => $value ? 'true' : 'false',
                'type' => 'literal',
                'extra' => ['datatype' => 'http://www.w3.org/2001/XMLSchema#boolean'],
            ]];
        }

        if (is_int($value)) {
            return [[
                'value' => (string) $value,
                'type' => 'literal',
                'extra' => ['datatype' => 'http://www.w3.org/2001/XMLSchema#integer'],
            ]];
        }

        if (is_float($value)) {
            return [[
                'value' => (string) $value,
                'type' => 'literal',
                'extra' => ['datatype' => 'http://www.w3.org/2001/XMLSchema#decimal'],
            ]];
        }

        return [[
            'value' => (string) $value,
            'type' => 'literal',
            'extra' => [],
        ]];
    }

    /** @return array<int, mixed> */
    private function asArray(mixed $value): array
    {
        return is_array($value) && $this->isList($value) ? $value : [$value];
    }

    private function isList(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1);
    }

    private function stripComment(string $line): string
    {
        $inString = false;
        $escaped = false;
        $length = strlen($line);

        for ($i = 0; $i < $length; $i++) {
            $char = $line[$i];

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === '"') {
                $inString = !$inString;
                continue;
            }

            if ($char === '#' && !$inString) {
                return substr($line, 0, $i);
            }
        }

        return $line;
    }

    /** @param array<int, string> $tokens */
    private function skipUntilDot(array $tokens, int &$i): void
    {
        $count = count($tokens);
        while ($i < $count && $tokens[$i] !== '.') {
            $i++;
        }
        if ($i < $count && $tokens[$i] === '.') {
            $i++;
        }
    }

    /** @param array<int, string> $tokens */
    private function skipUntilPredicateBoundary(array $tokens, int &$i): void
    {
        $count = count($tokens);
        while ($i < $count && !in_array($tokens[$i], [';', '.'], true)) {
            $i++;
        }
    }
}
