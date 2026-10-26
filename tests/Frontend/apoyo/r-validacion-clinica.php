<?php
/** RE-0.1.3: ayuda real por AJAX con SQLite efímero, sin instalación ni .env. */
require __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../Support/DosClinicas.php';
require_once __DIR__ . '/../../../controllers/IdentidadController.php';
require_once __DIR__ . '/../../../models/IntentoLogin.php';
require_once __DIR__ . '/../../../helpers/Csrf.php';

$db = DosClinicas::sqlite();
$db->exec('CREATE TABLE intentos_login (identificador TEXT PRIMARY KEY, intentos INTEGER, primer_intento TEXT, ultimo_intento TEXT, bloqueado_hasta TEXT)');
Security::definirAlmacenDeIntentos(new IntentoLogin($db));
$_SESSION = ['csrf_cuenta_validacion' => $argv[2] ?? ''];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '192.0.2.123';
$_POST = json_decode($argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
(new IdentidadController($db))->validar();
