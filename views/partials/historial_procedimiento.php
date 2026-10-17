<?php // C8.1 (H11): resumen plegable común de vacuna y desparasitación, sin lógica de negocio. ?>
<details class="historia-fila">
    <?php // C8.2: arriba fecha y producto; abajo clínica y laboratorio o tipo, como filaHistorialCompacto(). ?>
    <summary class="historia-fila__resumen">
        <span class="historia-fila__lineas">
            <span class="historia-fila__linea historia-fila__linea--principal">
                <time class="historia-fila__fecha"><?= $fechaCorta($registroHistoria['fecha_aplicacion']) ?></time>
                <strong class="historia-fila__titulo"><?= $e($registroHistoria['nombre_vacuna'] ?? $registroHistoria['producto'] ?? '') ?></strong>
            </span>
            <span class="historia-fila__linea historia-fila__linea--contexto">
                <span class="historia-fila__clinica"><?= $e($registroHistoria['clinica_nombre'] ?? 'Clínica actual') ?></span>
                <span class="historia-fila__chips"><?php if (($registroHistoria['laboratorio'] ?? $registroHistoria['tipo'] ?? '') !== ''): ?><span class="historia-fila__chip"><?= $e($registroHistoria['laboratorio'] ?? $registroHistoria['tipo']) ?></span><?php endif; ?></span>
            </span>
        </span>
        <i class="fas fa-chevron-down historia-fila__flecha" aria-hidden="true"></i>
    </summary>
    <div class="historia-fila__detalle">
        <p><strong>Clínica:</strong> <?= $e($registroHistoria['clinica_nombre'] ?? 'Clínica actual') ?></p>
        <p><strong>Registro:</strong> <?= $e($registroHistoria['nombre_vacuna'] ?? $registroHistoria['producto'] ?? '') ?></p>
        <p><?= $e($registroHistoria['laboratorio'] ?? $registroHistoria['tipo'] ?? '') ?> · <?= $e($registroHistoria['periodicidad'] ?? '') ?></p>
        <p><strong>Próxima aplicación:</strong> <?= $fechaCorta($registroHistoria['fecha_proxima_dosis'] ?? $registroHistoria['fecha_proxima'] ?? null) ?></p>
        <p><strong>Lote:</strong> <?= $e($registroHistoria['lote'] ?? 'Sin registro') ?></p>
        <p><?= $e($registroHistoria['observaciones'] ?? '') ?></p>
    </div>
</details>
