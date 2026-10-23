<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../../config/App.php';
if (session_status() === PHP_SESSION_NONE) {
    session_set_save_handler(static fn () => true,static fn () => true,static fn () => '',static fn () => true,static fn () => true,static fn () => 0);
    session_start();
}
$_SESSION = ['id_usuario' => ($argv[1] ?? '') === 'admin' ? 1 : 2];
$esAdminHorario = ($argv[1] ?? '') === 'admin';
Contexto::activar(Contexto::deClinica(1,'Norte',$esAdminHorario ? Roles::ADMIN : Roles::VETERINARIO),1);
require __DIR__ . '/../../../views/partials/urgencias_agenda.php';
require __DIR__ . '/../../../views/partials/ausencias_agenda.php';
