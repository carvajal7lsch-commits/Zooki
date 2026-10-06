<?php
/**
 * Indexa la documentación de Zooki en Algolia.
 *
 * Genera un registro por cada sección (encabezado H2/H3) de los documentos
 * publicados y los envía al índice configurado en .env. Las secciones largas
 * se parten en varios registros que comparten el atributo `seccion`.
 *
 *     php scripts/algolia_index.php              # reindexa siempre
 *     php scripts/algolia_index.php --si-cambio  # solo si los .md cambiaron
 *
 * El contenedor corre la segunda forma en cada arranque (docker/iniciar.sh),
 * así el índice sigue a la documentación desplegada sin que nadie se acuerde
 * de reindexar (specs/buscador-documentacion.md, BD-1).
 *
 * Se usa la API REST directamente con cURL (ya disponible en el proyecto)
 * en lugar del SDK de Algolia, para no sumar dependencias de composer por
 * un script que solo hace unas pocas llamadas.
 */

require_once dirname(__DIR__) . '/helpers/DocsIndex.php';
require_once dirname(__DIR__) . '/config/Algolia.php';

// Tope por registro. El límite del plan gratuito de Algolia es de 10 KB; lo
// que pasa de aquí va a otro registro de la misma sección (BD-3).
const MAX_CONTENT_BYTES = 6000;

// Sube cuando cambia la forma de los registros o los ajustes del índice:
// entra en la huella y obliga a reindexar aunque los .md no hayan cambiado.
const FORMATO_INDICE = 2;

$soloSiCambio = in_array('--si-cambio', $argv, true);
$config = Algolia::adminConfig();

if ($config['app_id'] === '' || $config['admin_key'] === '') {
    if ($soloSiCambio) {
        // En el arranque del contenedor no es un error: el portal usa el buscador local.
        echo "[algolia] Sin credenciales en .env; no se indexa la documentación.\n";
        exit(0);
    }
    fwrite(STDERR, "ERROR: faltan credenciales de Algolia.\n");
    fwrite(STDERR, "Define ALGOLIA_APP_ID y ALGOLIA_ADMIN_KEY en el archivo .env\n");
    fwrite(STDERR, "(ver .env.example para la plantilla).\n");
    exit(1);
}

$indexName = $config['index_name'];
$huella = huella_documentos();

if ($soloSiCambio) {
    $ajustes = algolia_request($config, 'GET', "/1/indexes/{$indexName}/settings", null, true);
    if (($ajustes['userData']['docsHash'] ?? null) === $huella) {
        echo "[algolia] La documentación no cambió; el índice ya está al día.\n";
        exit(0);
    }
}

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
        $seccion = $docId . '--' . $section['slug'];

        foreach (DocsIndex::fragmentar($section['raw'], MAX_CONTENT_BYTES) as $n => $trozo) {
            $records[] = [
                // objectID estable: reindexar actualiza el registro en vez de duplicarlo.
                'objectID' => $seccion . '--' . $n,
                'seccion'  => $seccion,
                'docId'    => $docId,
                'docTitle' => $docTitle,
                'heading'  => $section['text'],
                'level'    => $section['level'],
                'slug'     => $section['slug'],
                'codigos'  => DocsIndex::codigosDefinidos($trozo, $section['text']),
                'content'  => DocsIndex::cleanBody($trozo),
                // Enlace directo a la sección dentro del SPA.
                'url'      => '#' . $seccion,
                'position' => $position,
            ];
        }
    }

    echo "  - {$docId}: " . count($sections) . " secciones\n";
}

if (!$records) {
    fwrite(STDERR, "\nERROR: no se generó ningún registro. ¿Están los .md en documentacion/?\n");
    exit(1);
}

echo "\nTotal: " . count($records) . " registros\n\n";

// ── 2. Configurar el índice ──
// searchableAttributes en orden de prioridad: los códigos que la sección
// define (RN-411) pesan más que su título, y este más que el cuerpo, donde
// el código solo se menciona (BD-4). Algolia ya normaliza acentos y tolera
// errores de tipeo por defecto, salvo en números: «412» no debe traer 402.
// distinct sobre `seccion` deja una sola tarjeta por sección aunque esté
// partida en varios registros (BD-3). docId es filtrable para que el
// navegador pida primero los resultados del documento abierto (BD-5).
echo "Aplicando configuración del índice...\n";
algolia_request($config, 'PUT', "/1/indexes/{$indexName}/settings", [
    'searchableAttributes'      => ['codigos', 'heading', 'docTitle', 'content'],
    'attributesToHighlight'     => ['heading', 'content'],
    'attributesToSnippet'       => ['content:35'],
    'attributesForFaceting'     => ['filterOnly(docId)'],
    'attributeForDistinct'      => 'seccion',
    'distinct'                  => true,
    'allowTyposOnNumericTokens' => false,
    'customRanking'             => ['asc(position)'],
    'ignorePlurals'             => true,
    'queryLanguages'            => ['es'],
    'indexLanguages'            => ['es'],
]);

// ── 3. Reemplazar el contenido del índice ──
// Se limpia antes de subir para que las secciones eliminadas de los .md no
// queden como registros huérfanos. El índice queda vacío unos segundos y el
// portal cae a su buscador local mientras tanto (BD-2).
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

// La huella se guarda al final: si la subida falla a medias, el próximo
// arranque vuelve a intentarlo en vez de dar el índice por bueno.
algolia_request($config, 'PUT', "/1/indexes/{$indexName}/settings", [
    'userData' => ['docsHash' => $huella],
]);

echo "\nListo. La documentación quedó indexada en Algolia.\n";

/**
 * Huella de lo que se publica: el contenido de cada documento y la versión
 * del formato de los registros.
 */
function huella_documentos(): string
{
    $partes = ['formato:' . FORMATO_INDICE];
    foreach (DocsIndex::docsMap() as $docId => $filePath) {
        $partes[] = $docId . ':' . (file_exists($filePath) ? sha1_file($filePath) : '-');
    }

    return hash('sha256', implode("\n", $partes));
}

/**
 * Ejecuta una llamada a la API REST de Algolia y aborta si falla. Con
 * $permitir404, un índice que todavía no existe devuelve [] en vez de abortar.
 */
function algolia_request(array $config, string $method, string $path, ?array $body = null, bool $permitir404 = false): array
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

    if ($status === 404 && $permitir404) {
        return [];
    }

    if ($status < 200 || $status >= 300) {
        fwrite(STDERR, "\nERROR de Algolia (HTTP {$status}) en {$method} {$path}:\n{$response}\n");
        exit(1);
    }

    return json_decode($response, true) ?: [];
}
