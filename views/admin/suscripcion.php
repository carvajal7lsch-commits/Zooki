<?php
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$s = $estadoSuscripcion;
$contrato = $s['plan_contratado'];
$precio = static fn ($p): string => $p === null ? 'No disponible' : '$' . number_format((int) $p, 0, ',', '.') . ' COP';
$fecha = static fn ($f): string => $f === null ? 'Sin fecha' : VigenciaSuscripcion::fecha($f)->format('d/m/Y');
$incluida = static fn ($f): string => VigenciaSuscripcion::fechaIncluida($f);
$etiquetas = ['sin_vigencia' => 'Sin vigencia de pago', 'al_dia' => 'Al día', 'gracia' => 'En gracia', 'congelada' => 'Beneficios congelados'];
?>
<section class="suscripcion" aria-labelledby="suscripcionTitulo" data-suscripcion-pantalla data-pago-habilitado="<?= !empty($pagoDisponible) ? '1' : '0' ?>" data-version="<?= (int) ($s['suscripcion']['version_periodo'] ?? 0) ?>">
    <header class="suscripcion__cabecera">
        <div><p class="suscripcion__contexto"><?= $h($s['clinica']['nombre']) ?></p><h1 id="suscripcionTitulo">Suscripción</h1></div>
        <a class="suscripcion__volver" href="index.php?action=admin_panel">Volver al Inicio</a>
    </header>
    <div class="suscripcion__tarjetas">
        <article class="suscripcion__tarjeta">
            <h2>Tu plan</h2>
            <dl class="suscripcion__datos">
                <div><dt>Contratado</dt><dd><?= $h($contrato['nombre']) ?></dd></div>
                <div><dt>Efectivo hoy</dt><dd data-suscripcion-plan><?= $h($s['plan_efectivo']['nombre']) ?></dd></div>
                <div><dt>Estado</dt><dd data-suscripcion-estado><?= $h((int) $contrato['id_plan'] === (int) $s['plan_gratuito']['id_plan'] ? 'Gratuito · sin vencimiento' : $etiquetas[$s['situacion']]) ?></dd></div>
                <div><dt>Ciclo</dt><dd><?= $h($s['ciclo'] === 'anual' ? 'Anual' : 'Mensual') ?></dd></div>
                <div><dt>Último día del periodo</dt><dd data-suscripcion-fin><?= $h($incluida($s['periodo_fin'])) ?></dd></div>
                <div><dt>Último día con beneficios sin renovar</dt><dd><?= $h($incluida($s['gracia_hasta'])) ?></dd></div>
            </dl>
            <?php if ($s['legado_sin_fecha']): ?>
            <p class="suscripcion__nota" role="status">Tu plan fue asignado manualmente y aún no tiene vencimiento. Conserva sus condiciones; el super-administrador debe fijar la fecha antes de iniciar renovaciones.</p>
            <?php endif; ?>
            <?php if ($s['situacion'] === 'gracia'): ?>
            <p class="suscripcion__nota" role="status">Conservas los beneficios durante <?= (int) $s['clinica']['suscripcion_gracia_dias'] ?> días de gracia. Después se aplican los límites gratuitos a nuevas altas.</p>
            <?php endif; ?>
        </article>
        <article class="suscripcion__tarjeta">
            <h2>Profesional</h2>
            <?php
            $profesional = $profesionalSuscripcion;
            ?>
            <p class="suscripcion__precio"><?= $h($precio($profesional['precio_mensual'])) ?><span> al mes</span></p>
            <p><?= $h($precio($profesional['precio_anual'])) ?> al año<?= (int) $profesional['precio_anual'] === (int) $profesional['precio_mensual'] * 10 ? ' · paga 10, usa 12' : '' ?></p>
            <p>Sin límites de mascotas, citas ni cuentas de personal. Un cambio de precio conserva los periodos ya comprados.</p>
            <fieldset class="suscripcion__ciclos">
                <legend>Elige el periodo que vas a pagar</legend>
                <label><input type="radio" name="suscripcion_ciclo" value="mensual" checked> Mensual · <?= $h($precio($profesional['precio_mensual'])) ?></label>
                <label><input type="radio" name="suscripcion_ciclo" value="anual"> Anual · <?= $h($precio($profesional['precio_anual'])) ?></label>
            </fieldset>
            <button class="suscripcion__pagar" type="button" <?= empty($pagoDisponible) ? 'disabled' : '' ?> aria-describedby="suscripcionMensaje">Pagar/Renovar</button>
            <button class="suscripcion__consultar" type="button">Consultar estado</button>
            <p id="suscripcionMensaje" class="suscripcion__ayuda" role="status" aria-live="polite"><?= $h($mensajePago ?? 'Disponible pronto') ?></p>
        </article>
    </div>
    <?php if ($retornoPago !== null): ?>
    <p class="suscripcion__nota" role="status">Tu pago está <?= $h($retornoPago['pago']['estado']) ?>. Tu plan se activa en cuanto Wompi confirme el pago; puede tardar unos minutos. Puedes consultar su estado.</p>
    <?php endif; ?>
    <article class="suscripcion__tarjeta suscripcion__fuente" data-fuente-pantalla data-automatico-habilitado="<?= $automaticoDisponible ? '1' : '0' ?>">
        <h2>Medio de pago y renovación mensual</h2>
        <p data-fuente-resumen><?= $h($fuentePago ? ($fuentePago['tipo'] === 'NEQUI' ? 'Nequi' : (($fuentePago['marca'] ?: 'Tarjeta') . ($fuentePago['ultimos_cuatro'] ? ' · termina en ' . $fuentePago['ultimos_cuatro'] : ''))) : 'No tienes un medio guardado.') ?></p>
        <p data-fuente-estado><?= $h($fuentePago ? ($fuentePago['estado'] === 'disponible' ? 'Disponible' : 'No disponible') : '') ?></p>
        <p data-automatico-estado><?= $h($requiereAceptacion ? 'El precio cambió. Acepta el nuevo importe antes de renovar automáticamente.' : ($fuentePago && (int) $fuentePago['cobro_automatico'] === 1 ? 'Cobro mensual autorizado.' : 'Cobro automático desactivado.')) ?></p>
        <p><?= $h($mensajeAutomatico) ?></p>
        <p>Importe por renovación mensual: <strong data-importe-mensual><?= $h($precio($profesional['precio_mensual'])) ?></strong>. El cobro anual no es automático.</p>
        <button class="suscripcion__consultar" type="button" data-fuente-preparar <?= empty($pagoDisponible) ? 'disabled' : '' ?>>Consultar condiciones</button>
        <fieldset class="suscripcion__ciclos" data-fuente-condiciones hidden>
            <legend>Aceptaciones de Wompi</legend>
            <label><input type="checkbox" data-acepta-politica> He leído y acepto <a data-contrato-politica target="_blank" rel="noopener noreferrer">la política de Wompi</a>.</label>
            <label><input type="checkbox" data-acepta-datos> He leído y acepto <a data-contrato-datos target="_blank" rel="noopener noreferrer">el tratamiento de datos de Wompi</a>.</label>
            <p data-fuente-validacion class="suscripcion__ayuda" aria-live="polite"></p>
            <button class="suscripcion__consultar" type="button" data-fuente-guardar disabled>Guardar tarjeta o Nequi con Wompi</button>
            <label><input type="checkbox" data-acepta-mensual> Autorizo un cargo de <span data-mandato-importe><?= $h($precio($profesional['precio_mensual'])) ?></span> cada mes al vencer mi plan. Puedo retirar esta autorización.</label>
            <button class="suscripcion__consultar" type="button" data-automatico-autorizar disabled>Autorizar cobro mensual</button>
        </fieldset>
        <button class="suscripcion__consultar" type="button" data-nequi-comprobar hidden>Comprobar Nequi</button>
        <button class="suscripcion__consultar" type="button" data-automatico-retirar <?= !$fuentePago || !(int) $fuentePago['cobro_automatico'] ? 'hidden' : '' ?>>Retirar cobro automático</button>
        <button class="suscripcion__consultar" type="button" data-fuente-quitar <?= !$fuentePago ? 'hidden' : '' ?>>Quitar medio de pago</button>
        <p class="suscripcion__ayuda" role="status" aria-live="polite" data-fuente-mensaje></p>
    </article>
    <article class="suscripcion__tarjeta suscripcion__historial">
        <h2>Historial de pagos</h2>
        <?php if (!$historialPagos['filas']): ?>
        <p>Aún no hay pagos registrados. Las concesiones manuales no son pagos.</p>
        <?php endif; ?>
        <div class="suscripcion__pagos">
        <?php foreach ($historialPagos['filas'] as $pago): ?>
            <article class="suscripcion__pago">
                <p><strong><?= $h($precio(intdiv((int) $pago['total_centavos'], 100))) ?></strong> · <?= $h($pago['ciclo'] === 'anual' ? 'Anual' : 'Mensual') ?></p>
                <p><?= $h($pago['creado_en']) ?> · <?= $h($pago['estado']) ?> · <?= $h($pago['estado_intento']) ?></p>
                <p class="suscripcion__referencia">Referencia: <?= $h($pago['referencia']) ?></p>
                <?php if ($pago['inicio'] !== null): ?><p>Periodo: <?= $h($fecha($pago['inicio'])) ?> — <?= $h($incluida($pago['fin'])) ?> (incluido)</p><?php endif; ?>
                <?php if ($pago['codigo_error'] !== null): ?><p>En revisión: <?= $h($pago['codigo_error']) ?></p><?php endif; ?>
            </article>
        <?php endforeach; ?>
        </div>
        <nav class="suscripcion__paginacion" aria-label="Páginas del historial">
            <?php if ($historialPagos['pagina'] > 1): ?><a href="index.php?action=admin_suscripcion&amp;pagina=<?= (int) $historialPagos['pagina'] - 1 ?>">Anterior</a><?php endif; ?>
            <?php if ($historialPagos['mas']): ?><a href="index.php?action=admin_suscripcion&amp;pagina=<?= (int) $historialPagos['pagina'] + 1 ?>">Siguiente</a><?php endif; ?>
        </nav>
    </article>
    <p class="suscripcion__ayuda">Las fechas muestran el último día incluido. Tus datos e historia se conservan al congelar beneficios; las urgencias rojas siempre se atienden. Una suspensión de la clínica se gestiona aparte.</p>
</section>
