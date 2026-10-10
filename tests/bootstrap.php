<?php
/** D3: ninguna prueba puede caer en las credenciales de la aplicación. */
define('ZOOKI_PRUEBAS', true);
putenv('ZOOKI_ENTORNO_PRUEBAS=1');
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../helpers/Security.php';
Security::definirAlmacenDeIntentos(null);
if (getenv('ZOOKI_TEST_MYSQL_DB') && getenv('ZOOKI_TEST_MYSQL_DB') !== 'zooki_test_base_v2') {
    throw new RuntimeException('Las pruebas de MySQL solo usan zooki_test_base_v2.');
}
