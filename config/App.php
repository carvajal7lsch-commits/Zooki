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
     * módulo o un pulido; decisión del usuario, 2026-10-11), porque los CSS y
     * JS se piden con ?v=<versión> y sin cambiarla el navegador sigue con los
     * archivos viejos. 2.0.0 llega al completar la entrega v2.0 del plan de historias.
     */
    const VERSION = '2.0.0-alpha.4';

    /** Nombre comercial del producto. */
    const NAME = 'Zooki';

    /** Lema oficial de la marca. */
    const TAGLINE = 'Cuidamos a quienes amas';

    /** Ciudad y país de origen del proyecto. */
    const LOCATION = 'Neiva, Huila, Colombia';

    /**
     * Devuelve el sufijo de versión para invalidar la caché de un asset.
     * Se usa la versión del producto en lugar de time() para que el
     * navegador pueda cachear los archivos entre despliegues.
     */
    public static function assetVersion(): string {
        return self::VERSION;
    }
}
