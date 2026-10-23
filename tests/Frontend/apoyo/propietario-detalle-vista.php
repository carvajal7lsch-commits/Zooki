<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../../helpers/ValidadorMascota.php';
require_once __DIR__ . '/../../../config/App.php';
$mascotas = [];
?>
<header class="top-header top-header--pacientes">
    <div class="header-left"><?php require __DIR__ . '/../../../views/vet/partials/cabecera_pacientes.php'; ?></div>
    <div class="header-right"><span>Clínica Norte · Veterinario</span></div>
</header>
<main class="content-body">
<?php require __DIR__ . '/../../../views/vet/pacientes.php'; ?>
</main>
