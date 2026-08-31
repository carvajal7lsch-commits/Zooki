<?php
/**
 * Configuración global de la aplicación.
 * Responsabilidad única: exponer metadatos del producto y el versionado
 * de assets estáticos para el cache-busting de CSS/JS.
 */
class App {
    /** Versión actual del sistema (sincronizada con el historial del README). */
    const VERSION = '1.7.3';

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
