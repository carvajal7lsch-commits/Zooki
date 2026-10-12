<?php
/**
 * RE-T.19.3 — La política cambió de versión: se acepta antes de continuar.
 * Recibe de CuentaController $version, $vigencia y $error.
 * M0.2: marco del acceso y la casilla común de la política.
 */
require_once __DIR__ . '/../../helpers/Csrf.php';
$e = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$tituloPagina = 'Política de datos · Zooki';
$accesoSalida = 'sesion';
require __DIR__ . '/../partials/acceso_marco_inicio.php';
$estado = ['tipo' => 'info', 'icono' => 'ri-shield-check-line', 'titulo' => 'Actualizamos nuestra política de datos', 'texto' => 'Para seguir usando Zooki lee y acepta la versión ' . $version . ' de la Política de Tratamiento de Datos Personales, vigente desde el ' . $vigencia . '.'];
require __DIR__ . '/../partials/acceso_estado.php';
?>
            <?php if ($error): ?>
                <div class="registro-resumen" role="alert"><p><?= $e($error) ?></p></div>
            <?php endif; ?>
            <form method="POST" action="index.php?action=aceptar_politica" class="acceso-form">
                <?php Csrf::field('default'); ?>
                <?php $consentimientoId = 'aceptaPoliticaVigente'; $consentimientoVersion = $version; require __DIR__ . '/../partials/consentimiento_datos.php'; ?>
                <button type="submit" class="btn-primary"><span>Aceptar y continuar</span><i class="ri-arrow-right-line" aria-hidden="true"></i></button>
            </form>
<?php require __DIR__ . '/../partials/acceso_marco_fin.php'; ?>
</body>
</html>
