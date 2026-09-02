<?php
/**
 * Indexa la documentación de Zooki en Algolia.
 *
 * Genera un registro por cada sección (encabezado H2/H3) de los 9 documentos
 * y los envía al índice configurado en .env. Ejecutar tras cada cambio en
 * los .md:
 *
 *     php scripts/algolia_index.php
 *
 * Se usa la API REST directamente con cURL (ya disponible en el proyecto)
 * en lugar del SDK de Algolia, para no sumar dependencias de composer por
 * un script que solo hace tres llamadas.
 */

require_once dirname(__DIR__) . '/helpers/DocsIndex.php';
require_once dirname(__DIR__) . '/config/Algolia.php';

// Tope conservador por registro. El límite del plan gratuito de Algolia es
// de 10 KB; recortamos el cuerpo para no acercarnos al borde.
const MAX_CONTENT_BYTES = 7000;

$config = Algolia::adminConfig();

if ($config['app_id'] === '' || $config['admin_key'] === '') {
    fwrite(STDERR, "ERROR: faltan credenciales de Algolia.\n");
    fwrite(STDERR, "Define ALGOLIA_APP_ID y ALGOLIA_ADMIN_KEY en el archivo .env\n");
    fwrite(STDERR, "(ver .env.example para la plantilla).\n");
    exit(1);
}

$indexName = $config['index_name'];
echo "Indexando documentación en Algolia (índice: {$indexName})...\n\n";

// ── 1. Construir los registros a partir de los .md ──
$records = [];

foreach (DocsIndex::docsMap() as $docId => $filePath) {
    if (!file_exists($filePath)) {
        echo "  ! Omitido {$docId}: no existe " . basename($filePath) . "\n";
        continue;
    }

    $content = file_get_contents($filePath);
    $docTitle = DocsIndex::extractTitle($content);
    $sections = DocsIndex::extractHeadings($content, true);

    foreach ($sections as $position => $section) {
        $body = $section['content'] ?? '';
        if (strlen($body) > MAX_CONTENT_BYTES) {
            $body = mb_strcut($body, 0, MAX_CONTENT_BYTES, 'UTF-8');
        }

        $records[] = [
            // objectID estable: reindexar actualiza el registro en vez de duplicarlo.
            'objectID' => $docId . '--' . $section['slug'],
            'docId'    => $docId,
            'docTitle' => $docTitle,
            'heading'  => $section['text'],
            'level'    => $section['level'],
            'slug'     => $section['slug'],
            'content'  => $body,
            // Enlace directo a la sección dentro del SPA.
            'url'      => '#' . $docId . '--' . $section['slug'],
            'position' => $position,
        ];
    }

    echo "  - {$docId}: " . count($sections) . " secciones\n";
}

if (!$records) {
    fwrite(STDERR, "\nERROR: no se generó ningún registro. ¿Están los .md en la raíz?\n");
    exit(1);
}

echo "\nTotal: " . count($records) . " registros\n\n";

// ── 2. Configurar el índice ──
// searchableAttributes en orden de prioridad: el título de la sección pesa
// más que el cuerpo. Algolia ya normaliza acentos y tolera errores de tipeo
// por defecto, que es justo lo que el buscador local no hacía.
echo "Aplicando configuración del índice...\n";
algolia_request($config, 'PUT', "/1/indexes/{$indexName}/settings", [
    'searchableAttributes'  => ['heading', 'docTitle', 'content'],
    'attributesToHighlight' => ['heading', 'content'],
    'attributesToSnippet'   => ['content:35'],
    'customRanking'         => ['asc(position)'],
    'ignorePlurals'         => true,
    'queryLanguages'        => ['es'],
    'indexLanguages'        => ['es'],
]);

// ── 3. Reemplazar el contenido del índice ──
// Se limpia antes de subir para que las secciones eliminadas de los .md no
// queden como registros huérfanos. El índice queda vacío unos milisegundos:
// aceptable para un portal de documentación interno.
echo "Limpiando índice anterior...\n";
algolia_request($config, 'POST', "/1/indexes/{$indexName}/clear");

echo "Subiendo registros...\n";
foreach (array_chunk($records, 100) as $i => $chunk) {
    $requests = array_map(
        static fn(array $record): array => ['action' => 'addObject', 'body' => $record],
        $chunk
    );
    algolia_request($config, 'POST', "/1/indexes/{$indexName}/batch", ['requests' => $requests]);
    echo "  lote " . ($i + 1) . ": " . count($chunk) . " registros\n";
}

echo "\nListo. La documentación quedó indexada en Algolia.\n";

/**
 * Ejecuta una llamada a la API REST de Algolia y aborta si falla.
 */
function algolia_request(array $config, string $method, string $path, ?array $body = null): array
{
    $url = 'https://' . $config['app_id'] . '.algolia.net' . $path;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'X-Algolia-API-Key: ' . $config['admin_key'],
            'X-Algolia-Application-Id: ' . $config['app_id'],
            'Content-Type: application/json',
        ],
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        fwrite(STDERR, "\nERROR de red llamando a Algolia: {$curlError}\n");
        exit(1);
    }

    if ($status < 200 || $status >= 300) {
        fwrite(STDERR, "\nERROR de Algolia (HTTP {$status}) en {$method} {$path}:\n{$response}\n");
        exit(1);
    }

    return json_decode($response, true) ?: [];
}
