<?php
if (defined('ZOOKI_URGENCIAS_VISTA')) {
    echo '<link rel="stylesheet" href="css/agenda-herramientas.css">';
    echo '<script src="js/comando-agenda.js"></script>';
    require __DIR__ . '/../../../views/partials/urgencias_agenda.php';
    return;
}
require __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../Support/DosClinicas.php';
session_set_save_handler(static fn () => true, static fn () => true, static fn () => '', static fn () => true, static fn () => true, static fn () => 0);
session_start();
$db = DosClinicas::sqlite();
$esVet = ($argv[1] ?? '') === 'vet';
$_SESSION = ['id_usuario' => $esVet ? 2 : 1, 'usuario_nombre' => $esVet ? 'Beto Norte' : 'Ana Norte'];
$contexto = Contexto::deClinica(1, 'Clínica Norte (prueba)', $esVet ? Roles::VETERINARIO : Roles::ADMIN);
Contexto::activar($contexto, 1);
$_SESSION['contextos_total'] = 2;
Security::definirFuenteDeContextos(static fn (): array => [$contexto]);
$_GET = ['action' => $esVet ? 'vet_calendario' : 'admin_citas'];
if ($esVet) {
    define('ZOOKI_URGENCIAS_VISTA', true);
}
$content_view = $esVet ? __FILE__ : __DIR__ . '/../../../views/admin/citas.php';
require __DIR__ . ($esVet ? '/../../../views/vet/layout.php' : '/../../../views/admin/layout.php');
