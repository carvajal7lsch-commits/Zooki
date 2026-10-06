<?php
/**
 * Lógica compartida del portal de documentación: catálogo de documentos,
 * generación de slugs y extracción de secciones desde los .md.
 *
 * La usan tanto public/docs/index.php (endpoints get_doc / get_index) como
 * scripts/algolia_index.php (indexado en Algolia), para que el slug de un
 * encabezado sea idéntico en el enlace del sidebar, el ancla del DOM y el
 * registro de Algolia.
 *
 * NOTA: slugify() debe seguir produciendo el mismo resultado que la función
 * homónima de public/docs/docs.js. Si se edita una, edita también la otra.
 */
class DocsIndex
{
    /** Documentos publicados en el portal (evita Directory Traversal). */
    public static function docsMap(): array
    {
        $root = dirname(__DIR__);

        return [
            'readme'   => $root . '/README.md',
            'ficha'    => $root . '/documentacion/FichaTecnica_Zooki.md',
            'ers'      => $root . '/documentacion/ERS.md',
            'reglas'   => $root . '/documentacion/ReglasNegocio.md',
            'hu'       => $root . '/documentacion/HistoriasUsuario.md',
            're'       => $root . '/documentacion/RequisitosEspecificos.md',
            'mer'      => $root . '/documentacion/MER.md',
            'modelos'  => $root . '/documentacion/Modelos.md',
            'historial'=> $root . '/documentacion/HistorialVersiones.md',
            'roi'      => $root . '/documentacion/PresupuestoROI.md',
            // AnalisisVaciosDiseno.md NO se publica: documenta vulnerabilidades
            // sin corregir de un sistema en producción. Exponerlo sería
            // entregarle a un atacante el mapa de los riesgos actuales.
        ];
    }

    /**
     * Convierte el texto de un encabezado en un slug estable para anclas.
     * Se usa un mapa explícito de acentos en vez de Normalizer/NFD porque la
     * extensión intl no está garantizada en todos los entornos del proyecto.
     */
    public static function slugify(string $text): string
    {
        $accentMap = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n', 'Ü' => 'u',
        ];
        $text = strtr($text, $accentMap);
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^a-z0-9]+/u', '-', $text);

        return trim($text, '-');
    }

    /** Título del documento: su primer encabezado H1. */
    public static function extractTitle(string $content): string
    {
        if (preg_match('/^#[ \t]+(.+?)[ \t]*$/mu', $content, $match)) {
            return trim($match[1]);
        }

        return '';
    }

    /**
     * Extrae los encabezados H2/H3 (mismo criterio que generateTOC() en
     * docs.js, que solo recorre h2 y h3) con su slug deduplicado y, si se
     * pide, el cuerpo de texto que sigue a cada uno hasta el próximo
     * encabezado.
     *
     * @return array<int, array{level:int, text:string, slug:string, raw?:string, content?:string}>
     */
    public static function extractHeadings(string $content, bool $withBody = false): array
    {
        $headings = [];
        $used = [];

        $found = preg_match_all(
            '/^(#{2,3})[ \t]+(.+?)[ \t]*$/mu',
            $content,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        if (!$found) {
            return $headings;
        }

        foreach ($matches as $i => $match) {
            $level = strlen($match[1][0]);
            $text = trim($match[2][0]);

            $slug = self::slugify($text);
            if ($slug === '') {
                $slug = 'section';
            }
            if (isset($used[$slug])) {
                $used[$slug]++;
                $slug .= '-' . $used[$slug];
            } else {
                $used[$slug] = 1;
            }

            $heading = ['level' => $level, 'text' => $text, 'slug' => $slug];

            if ($withBody) {
                // El cuerpo va desde el final de esta línea de encabezado
                // hasta el inicio del siguiente encabezado (o el fin del texto).
                $start = $match[0][1] + strlen($match[0][0]);
                $end = isset($matches[$i + 1])
                    ? $matches[$i + 1][0][1]
                    : strlen($content);
                $heading['raw'] = substr($content, $start, $end - $start);
                $heading['content'] = self::cleanBody($heading['raw']);
            }

            $headings[] = $heading;
        }

        return $headings;
    }

    /**
     * Códigos de la documentación: RN-411, RN-G02, RNF-07, RF-4.7, RF-T.1,
     * RF-F.2, HU-4.13, HU-T.16, RE-0.1.1, RE-T.13.4. La letra del módulo (T,
     * F, G) puede ir seguida de punto: sin él, HU-T.1 y RF-T.1 no se
     * reconocían como código.
     */
    private const CODIGO = '(?:RNF|RN|RF|RE|HU)-(?:[A-Z]\.?)?\d+(?:\.\d+)*';

    /**
     * Códigos que una sección DEFINE, no los que solo menciona: los que abren
     * una fila de tabla (`| RN-411 | …`) y los que encabezan el título
     * (`### HU-15 — …`). El buscador los pondera por encima del cuerpo para
     * que buscar «412» lleve primero a la regla y después a quien la cita.
     *
     * @return string[]
     */
    public static function codigosDefinidos(string $cuerpoCrudo, string $encabezado = ''): array
    {
        $codigos = [];

        if (preg_match('/^' . self::CODIGO . '\b/u', trim($encabezado), $m)) {
            $codigos[] = $m[0];
        }

        preg_match_all('/^\|[ \t*]*(' . self::CODIGO . ')\b/mu', $cuerpoCrudo, $filas);
        foreach ($filas[1] as $codigo) {
            $codigos[] = $codigo;
        }

        return array_values(array_unique($codigos));
    }

    /**
     * Parte el cuerpo crudo de una sección en trozos de a lo sumo $maxBytes,
     * cortando entre líneas para no partir una fila de tabla. Antes la
     * sección se recortaba sin más y su final nunca llegaba al índice.
     * Los bloques de código se quitan primero: no se indexan, y partir uno
     * por la mitad dejaría a cleanBody() sin forma de reconocerlo.
     *
     * @return string[] Siempre al menos un trozo (vacío si la sección no tiene cuerpo).
     */
    public static function fragmentar(string $cuerpoCrudo, int $maxBytes): array
    {
        $cuerpo = preg_replace('/```.*?```/su', ' ', $cuerpoCrudo);
        $trozos = [];
        $actual = '';

        foreach (preg_split('/(?<=\n)/u', $cuerpo, -1, PREG_SPLIT_NO_EMPTY) as $linea) {
            // Una línea sola más larga que el tope se corta en límite de carácter.
            while (strlen($linea) > $maxBytes) {
                if ($actual !== '') {
                    $trozos[] = $actual;
                    $actual = '';
                }
                $corte = mb_strcut($linea, 0, $maxBytes, 'UTF-8');
                $trozos[] = $corte;
                $linea = substr($linea, strlen($corte));
            }

            if ($actual !== '' && strlen($actual) + strlen($linea) > $maxBytes) {
                $trozos[] = $actual;
                $actual = '';
            }
            $actual .= $linea;
        }

        if ($actual !== '' || !$trozos) {
            $trozos[] = $actual;
        }

        return $trozos;
    }

    /**
     * Deja el cuerpo de una sección legible para snippets de búsqueda:
     * quita la sintaxis Markdown más ruidosa y colapsa los espacios.
     */
    public static function cleanBody(string $body): string
    {
        // Bloques de código completos: aportan mucho ruido y poco valor de búsqueda.
        $body = preg_replace('/```.*?```/su', ' ', $body);
        // Marcadores de énfasis, encabezados residuales, viñetas y tablas.
        $body = preg_replace('/[*_`>|#]+/u', ' ', $body);
        // Enlaces Markdown: conservar el texto, descartar la URL.
        $body = preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', $body);
        $body = preg_replace('/\s+/u', ' ', $body);

        return trim($body);
    }
}
