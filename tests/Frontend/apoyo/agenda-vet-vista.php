<?php
// RNF-16: vistas reales, contexto de prueba y SQLite efímera; no lee la configuración local.
require __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../Support/DosClinicas.php';
session_set_save_handler(static fn () => true, static fn () => true, static fn () => '', static fn () => true, static fn () => true, static fn () => 0);
session_start();
$db = DosClinicas::sqlite();
$_SESSION = ['id_usuario' => 2, 'usuario_nombre' => 'Beto Norte', 'contextos_total' => 2];
$contexto = Contexto::deClinica(1, 'Clínica Norte (prueba)', Roles::VETERINARIO);
Contexto::activar($contexto, 1);
Security::definirFuenteDeContextos(static fn (): array => [$contexto]);
$_GET = ['action' => 'vet_agenda'];
$content_view = __DIR__ . '/../../../views/vet/calendario.php';
require __DIR__ . '/../../../views/vet/layout.php';
