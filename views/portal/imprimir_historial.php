<?php
require_once __DIR__ . '/../../config/App.php';
require_once __DIR__ . '/../../models/MarcaClinica.php';
require_once __DIR__ . '/../../helpers/ResumenPanel.php';
$e = static fn ($valor): string => htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
$fecha = static fn ($valor): string => $valor && $valor !== '0000-00-00' ? (new DateTimeImmutable($valor))->format('d/m/Y') : '—';
$emision = new DateTimeImmutable('now', new DateTimeZone('America/Bogota'));
$edad = ResumenPanel::edad($mascota['fecha_nacimiento'] ?? null, $emision) ?? '—';
$nombre = (string) $mascota['nombre'];
$inicial = mb_strtoupper(mb_substr($nombre, 0, 1));
$raza = !empty($mascota['raza_indicada']) ? $mascota['raza_indicada'] . ' (por confirmar)' : ($mascota['nombre_raza'] ?? '—');
// RN-114: orden cronológico de presentación sin alterar la API.
$cronologicas = $consultas;
usort($cronologicas, static fn (array $a, array $b): int => [$a['fecha_hora'], $a['id_consulta']] <=> [$b['fecha_hora'], $b['id_consulta']]);
// La procedencia queda en cada acto; la marca y el contacto se imprimen una vez por clínica.
$marcas = [];
foreach (array_merge($consultas, $vacunas, $desparasitaciones, $alertas) as $registro) {
    $marca = MarcaClinica::delRegistro($registro);
    if ($marca) $marcas[$marca['id_clinica']] = $marca;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historia clínica - <?= $e($nombre) ?> - Zooki</title>
    <link rel="stylesheet" href="css/identidad.css?v=<?= $e(App::assetVersion()) ?>">
    <link rel="stylesheet" href="css/imprimir-historial.css?v=<?= $e(App::assetVersion()) ?>">
</head>
<body>
    <nav class="floating-actions" aria-label="Acciones del documento">
        <a href="<?= $e($volver ?? 'index.php?action=portal_propietario') ?>" class="btn btn-secondary">← Volver</a>
        <button type="button" class="btn" data-ui-accion="imprimir">Imprimir / PDF</button>
    </nav>
    <main class="historial-documento">
        <header class="historial-cabecera">
            <strong class="historial-marca">Zooki</strong>
            <div><h1>Historia clínica</h1><p>Emitida el <?= $e($emision->format('d/m/Y H:i')) ?> · Bogotá</p></div>
        </header>
        <section class="historial-paciente" aria-labelledby="pacienteNombre">
            <div class="historial-identidad">
                <span class="historial-avatar" aria-hidden="true">
                    <span><?= $e($inicial) ?></span>
                    <?php if (!empty($mascota['url_foto'])): ?>
                        <img src="uploads/mascotas/<?= $e(rawurlencode($mascota['url_foto'])) ?>" alt="">
                    <?php endif; ?>
                </span>
                <div><p class="historial-etiqueta">Paciente</p><h2 id="pacienteNombre"><?= $e($nombre) ?></h2><p><?= $e($mascota['nombre_especie'] ?? '—') ?> · <?= $e($raza) ?></p></div>
            </div>
            <dl class="historial-datos">
                <div><dt>Sexo</dt><dd><?= $e($mascota['sexo'] ?? '—') ?></dd></div>
                <div><dt>Edad</dt><dd><?= $e($edad) ?><small>Nacimiento: <?= $e($fecha($mascota['fecha_nacimiento'] ?? null)) ?></small></dd></div>
                <div><dt>Peso registrado</dt><dd><?= $e(isset($mascota['peso']) && $mascota['peso'] !== '' ? $mascota['peso'] . ' kg' : 'Sin peso registrado') ?></dd></div>
                <div><dt>Propietario</dt><dd><?= $e($_SESSION['usuario_nombre'] ?? '—') ?></dd></div>
            </dl>
            <dl class="historial-hc">
                <?php foreach (($clinicas ?? []) as $clinica): ?>
                    <div><dt>HC · <?= $e($clinica['nombre']) ?></dt><dd><?= $e(($clinica['numero_historia_clinica'] ?? '') ?: '—') ?></dd></div>
                <?php endforeach; ?>
            </dl>
        </section>
        <section class="historial-alertas" aria-labelledby="alertasTitulo">
            <h2 id="alertasTitulo">Alergias y alertas activas</h2>
            <?php if (empty($alertas)): ?>
                <p class="historial-vacio">No hay alergias ni alertas registradas. Que no haya registro no significa que no tenga.</p>
            <?php else: ?>
                <?php foreach ($alertas as $alerta): ?>
                    <article class="historial-alerta"><strong><?= $e($alerta['tipo_nombre']) ?></strong><p><?= $e($alerta['descripcion']) ?></p><small><?= $e($alerta['clinica_nombre']) ?></small></article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
        <section aria-labelledby="vacunasTitulo">
            <h2 class="section-title" id="vacunasTitulo">Vacunación</h2>
            <?php if (empty($vacunas)): ?>
                <p class="historial-vacio">No se han registrado vacunas aplicadas.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>Vacuna / laboratorio</th><th>Clínica</th><th>Aplicada</th><th>Próxima dosis</th></tr></thead>
                    <tbody>
                        <?php foreach ($vacunas as $v): ?>
                            <tr><td><strong><?= $e($v['nombre_vacuna']) ?></strong><small><?= $e($v['laboratorio'] ?: '—') ?></small></td><td><?= $e($v['clinica_nombre']) ?></td><td><?= $e($fecha($v['fecha_aplicacion'])) ?></td><td><?= $e($fecha($v['fecha_proxima_dosis'])) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
        <section aria-labelledby="desparasitarTitulo">
            <h2 class="section-title" id="desparasitarTitulo">Controles antiparasitarios</h2>
            <?php if (empty($desparasitaciones)): ?>
                <p class="historial-vacio">No se han registrado controles antiparasitarios.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>Producto / tipo</th><th>Clínica</th><th>Aplicado</th><th>Próxima dosis</th></tr></thead>
                    <tbody>
                        <?php foreach ($desparasitaciones as $d): ?>
                            <tr><td><strong><?= $e($d['producto']) ?></strong><small><?= $e(ucfirst((string) $d['tipo']) . ' · ' . $d['periodicidad']) ?></small></td><td><?= $e($d['clinica_nombre']) ?></td><td><?= $e($fecha($d['fecha_aplicacion'])) ?></td><td><?= $e($fecha($d['fecha_proxima'])) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
        <section aria-labelledby="consultasTitulo">
            <h2 class="section-title" id="consultasTitulo">Consultas y diagnósticos</h2>
            <?php if (empty($cronologicas)): ?>
                <p class="historial-vacio">No hay consultas médicas registradas para esta mascota.</p>
            <?php else: ?>
                <?php foreach ($cronologicas as $con): ?>
                    <article class="historial-consulta">
                        <header><strong><?= $e((new DateTimeImmutable($con['fecha_hora']))->format('d/m/Y · H:i')) ?></strong><span><?= $e($con['clinica_nombre']) ?> · Dr(a). <?= $e($con['veterinario']) ?></span></header>
                        <dl>
                            <div><dt>Motivo</dt><dd><?= $e($con['motivo_consulta']) ?></dd></div>
                            <?php if (!empty($con['sintomas'])): ?>
                                <div><dt>Síntomas</dt><dd><?= $e(implode(', ', array_column($con['sintomas'], 'etiqueta'))) ?></dd></div>
                            <?php endif; ?>
                            <div><dt>Diagnóstico</dt><dd><?= $e($con['diagnostico']) ?></dd></div>
                            <div><dt>Plan</dt><dd><?= $e($con['plan_tratamiento']) ?></dd></div>
                        </dl>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
        <?php if ($marcas): ?>
            <section aria-labelledby="clinicasTitulo">
                <h2 class="section-title" id="clinicasTitulo">Clínicas de los registros</h2>
                <div class="historial-marcas">
                    <?php foreach ($marcas as $marca): ?>
                        <?= MarcaClinica::html($marca) ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
        <footer class="historial-pie">Generado por Zooki · Historia clínica de <?= $e($nombre) ?></footer>
    </main>
    <script src="js/imprimir-historial.js?v=<?= $e(App::assetVersion()) ?>"></script>
    <script src="js/interacciones.js?v=<?= $e(App::assetVersion()) ?>"></script>
</body>
</html>
