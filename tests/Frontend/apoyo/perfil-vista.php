<?php
require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../../models/Usuario.php';
require __DIR__ . '/../../../helpers/Csrf.php';
session_set_save_handler(static fn () => true, static fn () => true, static fn () => '', static fn () => true, static fn () => true, static fn () => 0);
session_start();
$_SESSION = ['id_usuario' => 2];
$rolPrueba = ($argv[1] ?? '') === 'vet' ? Roles::VETERINARIO : Roles::ADMIN;
Contexto::activar(Contexto::deClinica(1, 'Norte', $rolPrueba), 1);
if (($argv[1] ?? '') === 'config') {
    require __DIR__ . '/../../../config/App.php';
    require_once __DIR__ . '/../../../helpers/ValidadorCuenta.php';
    require_once __DIR__ . '/../../../helpers/ValidadorTelefono.php';
    $configuracion = ['marca' => ['id_clinica' => 1, 'nombre' => 'Clínica Norte', 'telefono' => '3001112233', 'email_contacto' => 'norte@zooki.test'], 'tipos' => []];
    require __DIR__ . '/../../../views/admin/configuracion.php';
    exit;
}
$rolNombre = Roles::nombre($rolPrueba);
$perfil = ['nombre_completo' => 'Fabio Propietario', 'email' => 'fabio@zooki.test', 'tipo_documento' => 'CC', 'documento' => '1000000006', 'telefono' => '3001112233', 'tiene_password' => true, 'tiene_google' => false];
$pideActual = true;
$cuentaGoogle = false;
// Configuración externa simulada: renderizar formularios nunca lee el .env local.
$clienteGoogleIdentidad = '';
$cuenta = ['miembro_desde' => 'octubre de 2025', 'acceso_anterior' => 'ayer', 'fallidos_30' => 0, 'disponible' => true, 'actividad' => []];
for ($i = 0; $i < 8; $i++) {
    $cuenta['actividad'][] = ['tipo' => 'acceso', 'titulo' => 'Acceso ' . ($i + 1), 'momento' => 'hace ' . ($i + 1) . ' días', 'ip' => '127.0.0.2'];
}
require __DIR__ . '/../../../views/perfil/index.php';
