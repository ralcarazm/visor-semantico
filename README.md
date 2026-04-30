# Visor Semántico

**Visor Semántico** es una aplicación web ligera para cargar ficheros RDF o datos tabulares equivalentes y generar un grafo visual interactivo. Está pensada para explorar relaciones entre entidades, obras, personas, organizaciones, lugares, conceptos y recursos externos como Wikidata o Wikimedia Commons.

- **Autor:** Rubén Alcaraz Martínez
- **Licencia:** GNU General Public License v3.0 o posterior

## Características principales

- Carga de ficheros RDF y tabulares desde la interfaz web.
- Generación automática de nodos y aristas a partir de tripletas.
- Visualización interactiva del grafo.
- Selección de nodo central y filtro por profundidad para explorar subgrafos.
- Enriquecimiento opcional de entidades de Wikidata.
- Recuperación de imágenes asociadas mediante Wikimedia Commons cuando están disponibles.
- Soporte para etiquetas, descripciones e imágenes declaradas directamente en el RDF.
- Exportación del grafo visible en JSON, SVG, PNG y WEBP.
- Panel de depuración para inspeccionar el JSON generado y las tripletas procesadas.
- Caché local de entidades e imágenes para reducir consultas repetidas.
- Configuración ampliable desde `config.php`.

## Formatos admitidos

La aplicación acepta ficheros con estas extensiones:

| Extensión | Formato | Observaciones |
|---|---|---|
| `.ttl` | Turtle | Soporte integrado para Turtle básico. |
| `.nt` | N-Triples | Soporte integrado. |
| `.n3` | Notation3 / Turtle básico | Tratado como Turtle básico. |
| `.rdf` | RDF/XML | Soporte integrado básico; para RDF/XML complejo se recomienda EasyRDF. |
| `.xml` | RDF/XML | Soporte integrado básico; para RDF/XML complejo se recomienda EasyRDF. |
| `.jsonld` | JSON-LD | Soporte integrado básico; para JSON-LD complejo se recomienda EasyRDF. |
| `.csv` | CSV | Debe contener tripletas. |
| `.tsv` | TSV | Debe contener tripletas. |

En CSV y TSV se recomienda usar esta cabecera:

```text
subject,predicate,object,object_type,lang,datatype
```

Las columnas mínimas son:

```text
subject,predicate,object
```

`object_type` puede ser `uri` o `literal`. Si se omite, la aplicación interpreta como URI los objetos que empiezan por `http://` o `https://`; el resto se tratan como literales.

## Vocabularios cubiertos por defecto

El visor puede procesar cualquier URI de predicado, aunque no esté registrada en `config.php`. La configuración incluye etiquetas legibles, criterios de visualización y grupos de nodos para vocabularios habituales:

- RDF y RDFS.
- SKOS básico.
- Dublin Core Element Set 1.1 (`dc`).
- DCMI Metadata Terms (`dcterms`).
- DCMI Type Vocabulary (`dcmitype`).
- FOAF.
- Schema.org.
- Europeana Data Model para propiedades de imagen frecuentes.
- Wikidata y Wikimedia Commons para enriquecimiento externo.

## Requisitos

### Obligatorios

- PHP 8.0 o superior.
- Servidor web con soporte PHP.
- Extensión `json` de PHP.
- Permisos de escritura en `storage/`.
- Navegador web moderno.

### Recomendados

- Extensión `curl` para consultar Wikidata y Wikimedia Commons.
- Extensión `mbstring` para tratamiento robusto de cadenas UTF-8.
- Extensión `simplexml` para mejorar el procesamiento de RDF/XML básico.
- Acceso a Internet desde el servidor si se quiere usar enriquecimiento externo.

### Dependencias opcionales

- **vis-network**: biblioteca JavaScript usada para la visualización interactiva cuando está disponible. La ruta prevista es:

```text
assets/vendor/vis-network/vis-network.min.js
```

Si no se puede cargar, la aplicación usa un renderizador alternativo básico.

- **EasyRDF**: biblioteca PHP opcional para mejorar el soporte de RDF/XML, JSON-LD, Turtle y otros casos RDF más complejos.

Para instalar EasyRDF con Composer:

```bash
composer require easyrdf/easyrdf
```

La aplicación lo detecta automáticamente si existe `vendor/autoload.php` y está disponible la clase `EasyRdf\Graph`.

## Instalación

1. Descarga o copia el proyecto en una carpeta servida por PHP.
2. Comprueba que el servidor puede escribir en `storage/` y sus subcarpetas.
3. Accede a `index.php` desde el navegador.
4. Pulsa **Comprobar servidor** para verificar que los endpoints básicos responden correctamente.
5. Carga uno de los ejemplos incluidos o sube un fichero propio.

Si las carpetas internas de `storage/` no existen, créalas manualmente:

```text
storage/uploads
storage/maps
storage/cache/entities
storage/cache/images
```

## Estructura del proyecto

```text
visor-semantico/
├── .htaccess
├── README.md
├── config.php
├── index.php
├── api/
├── app/
├── assets/
│   ├── css/
│   ├── js/
│   └── vendor/
├── samples/
├── storage/
│   ├── cache/
│   ├── maps/
│   └── uploads/
└── vendor/
```

Las carpetas `app/`, `storage/` y `vendor/` incluyen reglas `.htaccess` para evitar accesos directos no deseados desde el navegador.

## Uso básico

1. Abre la aplicación en el navegador.
2. Usa **Cargar ejemplo** para probar uno de los grafos de demostración.
3. Para usar datos propios, selecciona un fichero con una extensión admitida.
4. Pulsa **Subir y visualizar**.
5. Explora el grafo, filtra por nodo central o profundidad y revisa los detalles de cada nodo.
6. Exporta el resultado en el formato que necesites.

## Controles del grafo

- **Nodo central:** permite escoger una entidad como punto de partida. Su efecto principal se aprecia al combinarlo con el filtro de profundidad.
- **Profundidad:** limita el grafo a los nodos situados a 1, 2 o 3 saltos del nodo central, o muestra el grafo completo.
- **Centrar:** ajusta el grafo visible al espacio disponible.
- **Reorganizar:** reinicia la disposición visual de los nodos.
- **Zoom y movimiento:** se controlan con rueda de ratón, trackpad, arrastre, gesto de pinza o controles de zoom de la interfaz.

## Exportaciones

- **JSON visible:** exporta nodos, aristas, filtros aplicados y metadatos del grafo actualmente visible.
- **SVG:** exporta una representación vectorial adecuada para documentación, edición o publicación.
- **PNG:** exporta una imagen ráster con fondo transparente.
- **WEBP:** exporta una imagen web optimizada, siempre que el navegador soporte exportación WEBP desde canvas.

## Enriquecimiento con Wikidata y Wikimedia Commons

Cuando el enriquecimiento está activado, la aplicación detecta URIs de Wikidata como:

```text
https://www.wikidata.org/entity/Q252485
https://www.wikidata.org/wiki/Q252485
```

A partir de esas entidades intenta obtener:

- etiqueta preferente según el orden de idiomas configurado;
- descripción breve;
- imagen asociada mediante la propiedad `P18`;
- miniatura procedente de Wikimedia Commons.

El enriquecimiento no es imprescindible. Si el servidor no puede acceder a Internet o la consulta externa falla, el grafo se genera igualmente con los datos presentes en el fichero cargado.

Los datos enriquecidos y las miniaturas se guardan en:

```text
storage/cache/entities
storage/cache/images
```

Para refrescar los datos externos, se puede vaciar la caché desde el endpoint correspondiente o eliminando el contenido de esas carpetas.

## Configuración básica

El comportamiento principal se define en `config.php`. Las opciones más relevantes son:

- `app`: nombre, versión y modo de depuración.
- `paths`: rutas internas de subidas, mapas y caché.
- `upload`: tamaño máximo de subida y extensiones permitidas.
- `languages`: orden de preferencia para etiquetas y descripciones multilingües.
- `rdf.label_predicates`: propiedades usadas para obtener el nombre visible de un nodo.
- `rdf.description_predicates`: propiedades usadas como descripción.
- `rdf.image_predicates`: propiedades que pueden aportar imágenes.
- `rdf.hidden_predicates`: propiedades que no se muestran como aristas.
- `rdf.literal_node_predicates`: propiedades literales que pueden convertirse en nodos visibles.
- `rdf.predicate_labels`: traducciones legibles para las aristas.
- `rdf.type_groups`: asociación entre tipos RDF y grupos visuales.
- `enrichment`: activación y tiempo máximo de las consultas externas.
- `wikidata`: configuración de las consultas a Wikidata.
- `commons`: configuración de las consultas a Wikimedia Commons.

Para ampliar el soporte de un vocabulario nuevo, añade sus propiedades o tipos al bloque RDF correspondiente. Por ejemplo:

- propiedades de nombre en `label_predicates`;
- propiedades descriptivas en `description_predicates`;
- propiedades de imagen en `image_predicates`;
- propiedades técnicas o redundantes en `hidden_predicates`;
- propiedades categóricas literales en `literal_node_predicates`;
- etiquetas de aristas en `predicate_labels`;
- tipos RDF en `type_groups`.

## Solución de problemas

### El grafo dice que no hay nodos para visualizar

Posibles causas:

- El fichero no contiene tripletas válidas.
- Las URIs de sujeto u objeto están vacías.
- El RDF/XML usa construcciones no soportadas por el parser básico.
- El JSON-LD es demasiado complejo para el parser integrado.
- El fichero tiene una extensión no permitida.

Soluciones recomendadas:

- probar primero con Turtle o CSV/TSV;
- evitar `rdf:about=""` y `rdf:resource=""`;
- instalar EasyRDF si se van a procesar RDF/XML o JSON-LD complejos;
- revisar el panel JSON o el endpoint de depuración.

### No aparecen imágenes

Posibles causas:

- La entidad de Wikidata no tiene imagen `P18`.
- La imagen de Commons no se puede resolver desde el servidor.
- La URI de imagen declarada en el RDF no apunta a un recurso de imagen válido.
- El servidor no tiene acceso a Internet.
- La imagen no permite carga cruzada desde el navegador.

Soluciones recomendadas:

- declarar una imagen directa con `schema:image` o `foaf:depiction`;
- comprobar que la URL de imagen termina en un formato de imagen común;
- limpiar la caché si se ha corregido una entidad ya consultada.

### Las etiquetas aparecen en inglés

La aplicación respeta el orden configurado en `languages.preferred`. Si no existe una etiqueta en español, catalán o el idioma preferente, se usa el siguiente idioma disponible.

Para asegurar una etiqueta concreta, declárala en el fichero RDF:

```turtle
wd:Q17 schema:name "Japón"@es .
```

### Algunas propiedades no aparecen como aristas

Probablemente están en `hidden_predicates` o se usan internamente como etiquetas, descripciones, imágenes o tipos. Para mostrarlas como aristas, revisa `config.php` y retíralas de la lista correspondiente.

### El zoom o el movimiento no responden

Comprueba que se está usando una versión actual de estos ficheros:

```text
assets/js/app.js
assets/js/graph-viewer.js
assets/css/styles.css
```

## Licencia

Este proyecto se distribuye bajo los términos de la **GNU General Public License v3.0 o posterior**.

Puedes copiarlo, modificarlo y redistribuirlo bajo las condiciones de dicha licencia. Si redistribuyes versiones modificadas, debes mantener la misma licencia compatible y conservar los avisos de autoría y licencia.
