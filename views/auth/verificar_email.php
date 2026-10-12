<?php
/**
 * HU-36 / D2 — Confirmar el correo del registro con un botón (POST): abrir el
 * enlace no basta, porque los filtros de correo lo abren solos. M0.2: con el
 * marco del acceso (partials/acceso_marco_inicio.php).
 */
require_once __DIR__ . '/../../helpers/Csrf.php';
$e = static fn ($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$tituloPagina = 'Confirmar correo · Zooki';
require __DIR__ . '/../partials/acceso_marco_inicio.php';
$estado = ['tipo' => 'info', 'icono' => 'ri-mail-check-line', 'titulo' => 'Confirma tu correo', 'texto' => 'Pulsa el botón para confirmar que este correo es tuyo.'];
require __DIR__ . '/../partials/acceso_estado.php';
?>
            <form method="POST" action="index.php?action=verificar_email" class="acceso-form">
                <?php Csrf::field(); ?>
                <input type="hidden" name="id" value="<?= $e($id) ?>">
                <input type="hidden" name="token" value="<?= $e($token) ?>">
                <button type="submit" class="btn-primary"><span>Confirmar mi correo</span><i class="ri-check-line" aria-hidden="true"></i></button>
            </form>
            <p class="acceso-enlaces"><a class="acceso-enlace" href="index.php?action=login">Ir al inicio de sesión</a></p>
<?php require __DIR__ . '/../partials/acceso_marco_fin.php'; ?>
</body>
</html>
