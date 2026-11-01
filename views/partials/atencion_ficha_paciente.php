<?php
// RE-2.1.6, RNF-16: ficha lateral de lectura; reutiliza datos y alertas de Atención.
require_once __DIR__ . '/../../helpers/ResumenPanel.php';
$fechaCitaFicha = new DateTimeImmutable((string) $cita['fecha']);
$fechaLegibleFicha = ucfirst(ResumenPanel::fechaLarga($fechaCitaFicha)) . ' de ' . $fechaCitaFicha->format('Y');
?>
<dialog class="atencion-ficha-lateral" data-ficha-paciente aria-labelledby="fichaPacienteTitulo">
    <header class="atencion-ficha-lateral__cabecera">
        <span class="atencion-ficha-lateral__avatar"><?php if ($tieneFoto): ?><img src="<?= $e($fotoMascota) ?>" alt=""><?php else: ?><?= $e(PresentacionPlataforma::iniciales((string) ($mascota['nombre'] ?? '?'))) ?><?php endif; ?></span>
        <div><h2 id="fichaPacienteTitulo"><?= $e($mascota['nombre'] ?? 'Sin nombre') ?></h2><p>Ficha del paciente</p></div>
        <button type="button" class="atencion-ficha-lateral__cerrar" data-cerrar-ficha-paciente aria-label="Cerrar ficha del paciente"><i class="fas fa-times" aria-hidden="true"></i></button>
    </header>
    <div class="atencion-ficha-lateral__cuerpo">
        <section class="atencion-ficha-lateral__alertas" data-ficha-paciente-alertas aria-label="Alergias y alertas"></section>
        <section class="atencion-ficha-lateral__seccion">
            <h3>Paciente</h3>
            <dl class="atencion-ficha-lateral__datos">
                <div><dt>Especie</dt><dd data-ficha-paciente-dato="especie"><?= $e(($mascota['nombre_especie'] ?? '') ?: 'Sin registrar') ?></dd></div>
                <div><dt>Raza</dt><dd><?= $e(($mascota['nombre_raza'] ?? '') ?: 'Sin registrar') ?></dd></div>
                <div><dt>Sexo</dt><dd data-ficha-paciente-dato="sexo"><?= $e(($mascota['sexo'] ?? '') ?: 'Sin registrar') ?></dd></div>
                <div><dt>Edad</dt><dd data-ficha-paciente-dato="edad"><?= $e($edadMascota ?: 'Sin registrar') ?></dd></div>
                <div><dt>Peso de referencia</dt><dd data-ficha-paciente-dato="peso"><?= $pesoReferencia ? $textoPeso($pesoReferencia) : 'Sin peso registrado' ?></dd></div>
                <div><dt>Historia</dt><dd><?= $e(($mascota['numero_historia_clinica'] ?? '') ?: 'Nueva') ?></dd></div>
            </dl>
        </section>
        <section class="atencion-ficha-lateral__seccion">
            <h3>Propietario</h3>
            <div class="atencion-ficha-lateral__propietario"><span class="atencion-ficha-lateral__iniciales"><?= $e(PresentacionPlataforma::iniciales($nombrePropietario ?: '?')) ?></span><strong><?= $e($nombrePropietario ?: 'Sin registrar') ?></strong><?php if (!empty($propietario['telefono'])): ?><a class="atencion-ficha-lateral__telefono" href="tel:<?= $e(preg_replace('/[^0-9+]/', '', $propietario['telefono'])) ?>"><i class="fas fa-phone" aria-hidden="true"></i> <?= $e($propietario['telefono']) ?></a><?php endif; ?></div>
        </section>
        <section class="atencion-ficha-lateral__seccion">
            <h3>Esta cita</h3>
            <dl class="atencion-ficha-lateral__datos">
                <div><dt>Tipo</dt><dd><?= $e($tipoCitaNombre ?: 'Consulta general') ?></dd></div>
                <div><dt>Fecha</dt><dd><time datetime="<?= $e($cita['fecha'] . 'T' . substr($cita['hora'], 0, 5)) ?>"><?= $e($fechaLegibleFicha) ?> <span class="atencion-ficha-lateral__hora">· <?= $e(substr($cita['hora'], 0, 5)) ?></span></time></dd></div>
                <div><dt>Número</dt><dd>Cita #<?= (int) $cita['id_cita'] ?></dd></div>
                <div><dt>Estado</dt><dd><span class="atencion__estado estado--<?= $e($estadoActual) ?>"><i class="fas <?= $estadoActual === 'sin_cerrar' ? 'fa-exclamation-circle' : 'fa-circle' ?>" aria-hidden="true"></i> <?= $e($estadoLabel[$estadoActual] ?? $estadoActual) ?></span></dd></div>
            </dl>
        </section>
    </div>
    <footer class="atencion-ficha-lateral__pie"><button type="button" data-ficha-ver-historial>Ver historial completo</button></footer>
</dialog>
