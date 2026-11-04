<?php
/**
 * Configuración global de la aplicación.
 * Responsabilidad única: exponer metadatos del producto y el versionado
 * de assets estáticos para el cache-busting de CSS/JS.
 */
class App {
    /**
     * Versión actual del sistema (documentacion/HistorialVersiones.md). La v2
     * sale por partes: 2.0.0-alpha.N sube con cada entrega a producción (un
     * módulo o un pulido; decisión del usuario, 2026-10-11). El identificador
     * de construcción invalida CSS/JS también entre despliegues de la misma
     * versión. 2.0.0 llega al completar la entrega v2.0 del plan de historias.
     */
    const VERSION = '2.3.0';

    /** Nombre comercial del producto. */
    const NAME = 'Zooki';

    /** Lema oficial de la marca. */
    const TAGLINE = 'Cuidamos a quienes amas';

    /** Ciudad y país de origen del proyecto. */
    const LOCATION = 'Neiva, Huila, Colombia';

    private static ?string $versionDocker = null;

    /**
     * Devuelve el sufijo de versión para invalidar la caché de un asset.
     * Docker conserva la caché entre cargas, pero la invalida al desplegar;
     * XAMPP y las pruebas fuera de Docker conservan su comportamiento anterior.
     */
    public static function assetVersion(): string {
        if (file_exists('/.dockerenv')) {
            return self::versionDelDespliegue(dirname(__DIR__));
        }
        return defined('ZOOKI_PRUEBAS') ? self::VERSION : self::VERSION . '-dev' . time();
    }

    private static function versionDelDespliegue(string $raiz): string {
        if (self::$versionDocker !== null) return self::$versionDocker;
        $archivo = $raiz . '/VERSION_BUILD';
        $marca = is_readable($archivo) ? trim((string) file_get_contents($archivo)) : '';
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,79}\z/', $marca)) {
            return self::$versionDocker = self::VERSION . '-' . $marca;
        }

        // Imágenes anteriores sin marca: recorrer JS/CSS solo una vez y reutilizar el resultado.
        $ultimaModificacion = 0;
        foreach (['js', 'css'] as $tipo) {
            $carpeta = $raiz . '/public/' . $tipo;
            if (!is_dir($carpeta)) continue;
            $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($carpeta, FilesystemIterator::SKIP_DOTS));
            foreach ($archivos as $recurso) {
                if ($recurso->isFile()) $ultimaModificacion = max($ultimaModificacion, $recurso->getMTime());
            }
        }
        return self::$versionDocker = self::VERSION . '-assets' . $ultimaModificacion;
    }
}
