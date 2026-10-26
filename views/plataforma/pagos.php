<?php
/** RE-0.3.7: whitelist financiera del modelo; revisar nunca altera el pago. */
$e = static fn ($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$estados = ['al_dia' => 'Al día', 'gracia' => 'En gracia', 'congelada' => 'Congelada', 'legado_sin_fecha' => 'Legado sin fecha', 'sin_vigencia' => 'Sin periodo de pago'];
$motivos = ['precio' => 'Cambio de precio', 'condiciones' => 'Cambio de condiciones de Wompi', 'precio_y_condiciones' => 'Cambio de precio y condiciones de Wompi', 'precio_o_condiciones' => 'Cambio de precio o condiciones'];
$cargos = ['creado' => 'Por iniciar', 'enviando' => 'En curso', 'pendiente' => 'Pendiente', 'aprobado' => 'Aprobado', 'declinado' => 'Rechazado', 'error' => 'Fallido', 'anulado' => 'Anulado', 'incierto' => 'Por confirmar'];
$incidencias = ['REFERENCIA_DESCONOCIDA' => 'Referencia desconocida', 'DATOS_DISCORDANTES' => 'Datos discordantes', 'TRANSACCION_ANULADA' => 'Pago anulado', 'COBRO_DUPLICADO' => 'Pago duplicado', 'OBLIGACION_YA_MODIFICADA' => 'Suscripción modificada antes de acreditar'];
$dinero = static fn ($centavos) => '$' . number_format(intdiv((int) $centavos, 100), 0, ',', '.') . ' COP';
$resumen = $finanzas['resumen'];
?>
<div class="plataforma-finanzas" data-finanzas-contenido>
    <section aria-label="Conciliación por ID">
        <h4>Recuperar un pago de Wompi</h4>
        <p class="plataforma-ayuda">Consulta un pago existente. La referencia confirmada por Wompi identifica la clínica; esta acción no crea cargos.</p>
        <form class="plataforma-form" data-finanzas-conciliar>
            <?php Csrf::field(); ?>
            <label class="input-group">ID de transacción Wompi <input name="id_transaccion" required maxlength="<?= FirmaWompi::ID_MAX ?>" pattern="<?= $e(FirmaWompi::ID_PATRON) ?>" data-caracteres="a-zA-Z0-9_-" autocomplete="off"><small data-error-campo role="status"></small></label>
            <label class="input-group">Motivo <textarea name="motivo" rows="2" <?= ValidadorPlataforma::atributos() ?>></textarea><small data-error-campo role="status"></small></label>
            <p data-resultado role="status"></p>
            <button type="submit" class="btn-modal-primary">Conciliar</button>
        </form>
    </section>
    <?php if ($resumen): ?>
    <h4><?= $e($resumen['nombre']) ?></h4>
    <dl class="plataforma-finanzas__resumen">
        <div><dt>Plan contratado</dt><dd><?= $e($resumen['contratado']) ?></dd></div>
        <div><dt>Plan efectivo</dt><dd><?= $e($resumen['efectivo']) ?></dd></div>
        <div><dt>Estado</dt><dd><?= $e($estados[$resumen['situacion']] ?? 'Sin periodo de pago') ?></dd></div>
        <div><dt>Último día incluido</dt><dd><?= $e($resumen['fin']) ?></dd></div>
        <div><dt>Cobro automático</dt><dd><?= $resumen['automatico'] ? 'Sí' : 'No' ?></dd></div>
    </dl>
    <?php if (!$resumen['automatico'] && $resumen['motivo_apagado']): ?>
    <p class="plataforma-nota"><?= $e($motivos[$resumen['motivo_apagado']] ?? 'Cambió la autorización') ?>. Requiere nueva aceptación del administrador.</p>
    <?php endif; ?>
    <section aria-label="Historial de pagos">
        <h4>Historial de pagos</h4>
        <p class="plataforma-ayuda">Cada intento conserva su estado. Solo un pago aprobado concede el periodo indicado.</p>
        <?php if (!$finanzas['pagos']): ?><p>No hay pagos registrados.</p><?php endif; ?>
        <?php foreach ($finanzas['pagos'] as $pago): ?>
        <article class="plataforma-finanzas__registro">
            <strong><?= $e($dinero($pago['total_centavos'])) ?> · <?= $e(ucfirst($pago['ciclo'])) ?></strong>
            <p><?= $e(PresentacionPlataforma::fecha($pago['fecha'])) ?> · <?= $e($cargos[$pago['estado']] ?? 'En revisión') ?> · <?= $pago['entorno'] === 'test' ? 'Pruebas' : 'Producción' ?></p>
            <p>Referencia: <span><?= $e($pago['referencia']) ?></span></p>
            <p>Periodo concedido: <?= $pago['fin'] ? $e(PresentacionPlataforma::fecha($pago['inicio']) . ' al ' . VigenciaSuscripcion::fechaIncluida($pago['fin'])) : 'Sin periodo concedido' ?></p>
        </article>
        <?php endforeach; ?>
        <nav class="plataforma-finanzas__paginas" aria-label="Páginas de pagos">
            <?php if ($finanzas['pagina'] > 1): ?><button type="button" class="btn-modal-secondary" data-finanzas-pagina="<?= (int) $finanzas['pagina'] - 1 ?>">Anterior</button><?php endif; ?>
            <span>Página <?= (int) $finanzas['pagina'] ?></span>
            <?php if ($finanzas['hay_mas']): ?><button type="button" class="btn-modal-secondary" data-finanzas-pagina="<?= (int) $finanzas['pagina'] + 1 ?>">Siguiente</button><?php endif; ?>
        </nav>
    </section>
    <?php endif; ?>
    <section aria-label="Incidencias financieras">
        <h4>Incidencias<?= $resumen ? '' : ' · Sin clínica identificada' ?></h4>
        <?php if (!$finanzas['incidencias']): ?><p>No hay incidencias registradas.</p><?php endif; ?>
        <?php foreach ($finanzas['incidencias'] as $incidencia): ?>
        <article class="plataforma-finanzas__registro">
            <strong><?= $e($incidencias[$incidencia['codigo_error']] ?? 'Incidencia financiera') ?></strong>
            <p><?= $e(PresentacionPlataforma::fecha($incidencia['recibido_en'])) ?> · <?= $incidencia['entorno'] === 'test' ? 'Pruebas' : 'Producción' ?></p>
            <p>Referencia: <?= $e($incidencia['referencia'] ?? 'Sin referencia local') ?></p>
            <p>Transacción Wompi: <?= $e($incidencia['id_transaccion_wompi']) ?></p>
            <?php if ($incidencia['revisado_en']): ?>
            <p>Revisada el <?= $e(PresentacionPlataforma::fecha($incidencia['revisado_en'])) ?></p>
            <p><?= $e($incidencia['nota_revision']) ?></p>
            <?php else: ?>
            <form class="plataforma-form" data-finanzas-revision>
                <?php Csrf::field(); ?>
                <input type="hidden" name="id_evento" value="<?= (int) $incidencia['id_evento'] ?>">
                <input type="hidden" name="id_clinica" value="<?= (int) $finanzas['id_clinica'] ?>">
                <label class="input-group">Nota de revisión <textarea name="motivo" rows="2" <?= ValidadorPlataforma::atributos() ?>></textarea><small data-error-campo role="status"></small></label>
                <p data-resultado role="status"></p>
                <button type="submit" class="btn-modal-primary">Marcar como revisada</button>
            </form>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
    </section>
    <?php if ($resumen): ?>
    <section aria-label="Concesiones administrativas">
        <h4>Concesiones administrativas</h4>
        <p class="plataforma-ayuda">Se registran aparte de los pagos. El ajuste con motivo y fecha se conserva en «Plan y suscripción».</p>
        <?php if (!$finanzas['ajustes']): ?><p>No hay concesiones registradas<?= $resumen['situacion'] === 'legado_sin_fecha' ? '; el legado conserva sus beneficios sin inventar un pago ni fecha' : '' ?>.</p><?php endif; ?>
        <?php foreach ($finanzas['ajustes'] as $ajuste): ?>
        <article class="plataforma-finanzas__registro">
            <strong><?= $ajuste['origen'] === 'legado' ? 'Legado' : 'Ajuste manual' ?> · <?= $e(PresentacionPlataforma::fecha($ajuste['creado_en'])) ?></strong>
            <p><?= $e(PresentacionPlataforma::fecha($ajuste['inicio']) . ' al ' . VigenciaSuscripcion::fechaIncluida($ajuste['fin'])) ?> · <?= $e(ucfirst($ajuste['ciclo'])) ?></p>
            <p><?= $e($ajuste['motivo']) ?></p>
        </article>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>
</div>
