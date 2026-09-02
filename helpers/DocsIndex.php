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
            'ficha'    => $root . '/FichaTecnica_Zooki.md',
            'ers'      => $root . '/ERS.md',
            'reglas'   => $root . '/ReglasNegocio.md',
            'hu'       => $root . '/HistoriasUsuario.md',
            're'       => $root . '/RequisitosEspecificos.md',
            'mer'      => $root . '/MER.md',
            'backlog'  => $root . '/backlog-zooki.md',
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
     * @return array<int, array{level:int, text:string, slug:string, content?:string}>
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
                $heading['content'] = self::cleanBody(substr($content, $start, $end - $start));
            }

            $headings[] = $heading;
        }

        return $headings;
    }

    /**
     * Deja el cuerpo de una sección legible para snippets de búsqueda:
     * quita la sintaxis Markdown más ruidosa y colapsa los espacios.
     */
    private static function cleanBody(string $body): string
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
