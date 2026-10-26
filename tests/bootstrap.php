<?php
/** D3: ninguna prueba puede caer en las credenciales de la aplicación. */
define('ZOOKI_PRUEBAS', true);
putenv('ZOOKI_ENTORNO_PRUEBAS=1');
// Igual que el arranque real: el php.ini de XAMPP usa Berlin y su hora repetida
// de octubre hacía ambiguos los vencimientos de las pruebas sin motor.
require_once __DIR__ . '/../helpers/ZonaHoraria.php';
ZonaHoraria::aplicar();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../helpers/Security.php';
Security::definirAlmacenDeIntentos(null);
if (getenv('ZOOKI_TEST_MYSQL_DB') && getenv('ZOOKI_TEST_MYSQL_DB') !== 'zooki_test_base_v2') {
    throw new RuntimeException('Las pruebas de MySQL solo usan zooki_test_base_v2.');
}
