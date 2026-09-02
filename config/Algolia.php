<?php
/**
 * Configuración del buscador Algolia del portal de documentación.
 *
 * Las credenciales se leen de .env (ignorado por git). Distinguimos dos
 * llaves con propósitos distintos:
 *
 *  - ALGOLIA_SEARCH_KEY: llave de solo búsqueda. Está diseñada para viajar
 *    al navegador; es la única que se expone en el frontend.
 *  - ALGOLIA_ADMIN_KEY: llave de escritura, usada SOLO por
 *    scripts/algolia_index.php desde la línea de comandos. Nunca debe
 *    llegar al HTML ni al JavaScript.
 *
 * Si no hay credenciales configuradas, isEnabled() devuelve false y el
 * portal sigue funcionando con su buscador local (fallback).
 */
class Algolia
{
    private static ?array $config = null;

    private static function load(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $env = [];
        $envFile = dirname(__DIR__) . '/.env';
        if (file_exists($envFile)) {
            $env = parse_ini_file($envFile) ?: [];
        }

        self::$config = [
            'app_id'     => $env['ALGOLIA_APP_ID'] ?? '',
            'search_key' => $env['ALGOLIA_SEARCH_KEY'] ?? '',
            'admin_key'  => $env['ALGOLIA_ADMIN_KEY'] ?? '',
            'index_name' => $env['ALGOLIA_INDEX_NAME'] ?? 'zooki_docs',
        ];

        return self::$config;
    }

    /** ¿Hay credenciales de búsqueda para usar Algolia en el frontend? */
    public static function isEnabled(): bool
    {
        $config = self::load();

        return $config['app_id'] !== '' && $config['search_key'] !== '';
    }

    /**
     * Configuración pública para el frontend. Solo incluye la llave de
     * solo búsqueda: la de administración jamás sale de la CLI.
     */
    public static function publicConfig(): array
    {
        $config = self::load();

        return [
            'appId'     => $config['app_id'],
            'searchKey' => $config['search_key'],
            'indexName' => $config['index_name'],
        ];
    }

    /** Configuración completa, para el script de indexado (CLI). */
    public static function adminConfig(): array
    {
        return self::load();
    }
}
