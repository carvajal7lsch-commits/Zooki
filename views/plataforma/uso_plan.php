<dl class="plataforma-uso" aria-label="Uso del plan">
    <?php foreach ($clinica['uso_plan'] as $recurso => $cupo): ?>
    <div><dt><?= $e(ucfirst($recurso) . ($recurso === 'citas' ? ' del mes' : '')) ?></dt><dd><?= (int) $cupo['uso'] ?> / <?= $cupo['limite'] === null ? 'Sin límite' : (int) $cupo['limite'] ?></dd></div>
    <?php endforeach; ?>
</dl>
