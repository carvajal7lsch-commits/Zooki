<?php
require_once __DIR__ . '/ValidadorConocimiento.php';

/** Lee una instantánea completa antes de entregar datos al modelo de escritura. */
final class ArchivosConocimiento
{
    public static function leer(string $carpeta): array
    {
        $conjunto = [];
        $contenido = [];
        foreach (['manifiesto.json', ...ValidadorConocimiento::ARCHIVOS] as $archivo) {
            $ruta = rtrim($carpeta, '/\\') . '/' . $archivo;
            if (!is_file($ruta) || !is_readable($ruta)) {
                throw new InvalidArgumentException('Falta el archivo de conocimiento: ' . $archivo);
            }
            $texto = file_get_contents($ruta);
            if ($texto === false) {
                throw new RuntimeException('No se pudo leer: ' . $archivo);
            }
            try {
                $datos = json_decode($texto, true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException $error) {
                throw new InvalidArgumentException('JSON inválido en ' . $archivo, 0, $error);
            }
            if (!is_array($datos)) {
                throw new InvalidArgumentException('Estructura no válida en ' . $archivo);
            }
            // B2.1: el mismo conjunto debe conservar su versión entre Windows y Linux.
            $contenido[$archivo] = str_replace("\r\n", "\n", $texto);
            $conjunto[pathinfo($archivo, PATHINFO_FILENAME)] = $datos;
        }
        ValidadorConocimiento::validar($conjunto);
        ksort($contenido);
        // Incluye el manifiesto y todos los contenidos con finales LF, sin hashes manuales.
        $version = hash('sha256', json_encode($contenido, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return ['datos' => $conjunto, 'version' => $version];
    }

    /** Compartido por el índice de sinónimos y el futuro buscador de C4. */
    public static function normalizarTexto(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        $texto = preg_replace('/\p{Mn}/u', '', $texto);
        return preg_replace('/\s+/u', ' ', $texto);
    }
}
