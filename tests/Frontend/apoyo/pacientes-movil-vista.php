<?php
// RNF-16: layout/vista reales con contexto de prueba y SQLite efímera, sin configuración local.
require __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../../helpers/ValidadorMascota.php';
require_once __DIR__ . '/../../Support/DosClinicas.php';
session_set_save_handler(static fn () => true, static fn () => true, static fn () => '', static fn () => true, static fn () => true, static fn () => 0);
session_start();
$db = DosClinicas::sqlite();
$_SESSION = ['id_usuario' => 2, 'usuario_nombre' => 'Beto Norte', 'contextos_total' => 2];
$contexto = Contexto::deClinica(1, 'Clínica Norte (prueba)', Roles::VETERINARIO);
Contexto::activar($contexto, 1);
Security::definirFuenteDeContextos(static fn (): array => [$contexto]);
$_GET = ['action' => 'vet_pacientes'];
$mascotas = [];
if (($argv[1] ?? '') === 'directorio') {
    require __DIR__ . '/pacientes-directorio-datos.php';
    $mascotas = pacientesDirectorioDatos();
}
$content_view = __DIR__ . '/../../../views/vet/pacientes.php';
require __DIR__ . '/../../../views/vet/layout.php';
