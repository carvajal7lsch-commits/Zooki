<?php // C8.1 (H11): resumen plegable común de vacuna y desparasitación, sin lógica de negocio. ?>
<details class="historia-fila">
    <summary class="historia-fila__resumen">
        <time class="historia-fila__fecha"><?= $fechaCorta($registroHistoria['fecha_aplicacion']) ?></time>
        <span class="historia-fila__clinica"><?= $e($registroHistoria['clinica_nombre'] ?? 'Clínica actual') ?></span>
        <strong class="historia-fila__motivo"><?= $e($registroHistoria['nombre_vacuna'] ?? $registroHistoria['producto'] ?? '') ?></strong>
        <span class="historia-fila__diagnostico"><?= $e($registroHistoria['laboratorio'] ?? $registroHistoria['tipo'] ?? '') ?></span>
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
