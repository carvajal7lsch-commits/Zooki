<?php
require_once __DIR__ . '/../../helpers/ValidadorCuenta.php';
/**
 * RE-T.18.3 (RN-G20) — Una cuenta de Google registra su documento y su
 * teléfono antes de usar el portal. Recibe de CuentaController $error.
 * M0.2: marco del acceso; los campos y sus avisos como en los registros.
 */
require_once __DIR__ . '/../../helpers/Csrf.php';
require_once __DIR__ . '/../../helpers/ValidadorTelefono.php';
require_once __DIR__ . '/../../models/Usuario.php';
require_once __DIR__ . '/../../config/App.php';

$e = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$tituloPagina = 'Completa tu perfil · Zooki';
$accesoSalida = 'sesion';
// M5 C4a (punto 11): teléfono con bandera, como en el registro.
$accesoTelefono = true;
require __DIR__ . '/../partials/acceso_marco_inicio.php';
$estado = ['tipo' => 'info', 'icono' => 'ri-user-settings-line', 'titulo' => 'Completa tu perfil', 'texto' => 'Para registrar tus mascotas y agendar citas, la clínica necesita tu documento y un teléfono de contacto.'];
require __DIR__ . '/../partials/acceso_estado.php';
?>
            <?php if ($error): ?>
                <div class="registro-resumen" role="alert"><p><?= $e($error) ?></p></div>
            <?php endif; ?>
            <form method="POST" action="index.php?action=completar_perfil" class="acceso-form" data-validacion-cuenta>
                <?php Csrf::field('default'); ?>
                <div class="registro-grid">
                    <div class="input-group">
                        <label for="perfilTipoDocumento">Tipo de documento</label>
                        <div class="input-wrapper">
                            <select id="perfilTipoDocumento" name="tipo_documento" required>
                                <?php foreach (Usuario::TIPOS_DOCUMENTO as $tipo): ?>
                                    <option value="<?= $e($tipo) ?>"><?= $e(Usuario::NOMBRES_TIPO_DOCUMENTO[$tipo] ?? $tipo) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="input-group">
                        <label for="perfilDocumento">Número de documento</label>
                        <div class="input-wrapper"><input <?= ValidadorCuenta::atributosHtml('documento') ?> type="text" id="perfilDocumento" name="documento" placeholder="Ej. 1075..." required pattern="\d{5,15}" autocomplete="off"></div>
                    </div>
                    <div class="input-group registro-ancho">
                        <label for="perfilTelefono">Teléfono</label>
                        <div class="input-wrapper"><input type="tel" id="perfilTelefono" name="telefono" placeholder="Ej: 300 123 4567" required minlength="<?= ValidadorTelefono::MIN ?>" <?= ValidadorTelefono::atributosHtml() ?> data-telefono-internacional autocomplete="tel"></div>
                    </div>
                </div>
                <button type="submit" class="btn-primary"><span>Guardar y continuar</span><i class="ri-arrow-right-line" aria-hidden="true"></i></button>
            </form>
<?php require __DIR__ . '/../partials/acceso_marco_fin.php'; ?>
<?php require __DIR__ . '/../partials/validacion_cuenta.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.10/build/js/intlTelInput.min.js"></script>
<script src="js/telefono-internacional.js?v=<?= $e(App::assetVersion()) ?>"></script>
</body>
</html>
