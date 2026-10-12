<?php
/**
 * HU-T.17 — Selector de contexto. Recibe de ContextoController:
 * $disponibles (contextos vigentes), $actual (el activo o null), $nombre y $error.
 * M0.2: marco del acceso; cada contexto es una opción como las de «Crear cuenta».
 */
require_once __DIR__ . '/../../helpers/Csrf.php';
require_once __DIR__ . '/../../helpers/Contexto.php';

$e = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$iconos = [
    Contexto::CLINICA => 'ri-hospital-line',
    Contexto::PROPIETARIO => 'ri-heart-3-line',
    Contexto::PLATAFORMA => 'ri-shield-user-line',
];
$tituloPagina = 'Elige cómo entrar · Zooki';
$accesoSalida = 'sesion';
require __DIR__ . '/../partials/acceso_marco_inicio.php';
$saludo = 'Hola, ' . (explode(' ', trim((string) $nombre))[0] ?: 'de nuevo');
$estado = $disponibles
    ? ['tipo' => 'info', 'icono' => 'ri-user-shared-line', 'titulo' => $saludo, 'texto' => 'Elige con qué perfil quieres continuar. Puedes cambiarlo después sin cerrar sesión.']
    : ['tipo' => 'aviso', 'icono' => 'ri-user-shared-line', 'titulo' => $saludo, 'texto' => 'Tu cuenta todavía no tiene acceso a ninguna clínica. Cuando una clínica te vincule como parte de su personal o como propietario, podrás entrar desde aquí.'];
require __DIR__ . '/../partials/acceso_estado.php';
?>
            <?php if ($error): ?>
                <div class="registro-resumen" role="alert"><p><?= $e($error) ?></p></div>
            <?php endif; ?>
            <?php if ($disponibles): ?>
            <ul class="crear-cuenta-opciones acceso-contextos">
                <?php foreach ($disponibles as $c): ?>
                    <?php $esActual = $actual !== null && $actual['clave'] === $c['clave']; ?>
                    <li>
                        <form method="POST" action="index.php?action=cambiar_contexto">
                            <?php Csrf::field('default'); ?>
                            <input type="hidden" name="contexto" value="<?= $e($c['clave']) ?>">
                            <button type="submit" class="crear-cuenta-opcion<?= $esActual ? ' is-actual' : '' ?>">
                                <span class="crear-cuenta-icono"><i class="<?= $e($iconos[$c['tipo']] ?? 'ri-user-line') ?>" aria-hidden="true"></i></span>
                                <span class="crear-cuenta-texto">
                                    <strong><?= $e($c['tipo'] === Contexto::CLINICA ? $c['clinica'] : Contexto::etiqueta($c)) ?></strong>
                                    <span><?= $e($c['rol']) ?><?= $esActual ? ' · actual' : '' ?></span>
                                </span>
                                <i class="ri-arrow-right-s-line crear-cuenta-flecha" aria-hidden="true"></i>
                            </button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
<?php require __DIR__ . '/../partials/acceso_marco_fin.php'; ?>
</body>
</html>
