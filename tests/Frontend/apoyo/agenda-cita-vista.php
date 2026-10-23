<?php
require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../../config/App.php';
require __DIR__ . '/../../../helpers/Csrf.php';
session_set_save_handler(static fn () => true, static fn () => true, static fn () => '', static fn () => true, static fn () => true, static fn () => 0);
session_start();
$_SESSION = ['id_usuario' => 2];
Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::VETERINARIO), 1);
require __DIR__ . '/../../../views/vet/calendario.php';
