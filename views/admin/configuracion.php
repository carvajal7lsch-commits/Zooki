<?php
require_once __DIR__ . '/../../helpers/ValidadorConfiguracionClinica.php';
require_once __DIR__ . '/../../helpers/Csrf.php';
require_once __DIR__ . '/../../models/MarcaClinica.php';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$reglas = ['nombreMin' => ValidadorClinica::NOMBRE_MIN, 'nombreMax' => ValidadorClinica::NOMBRE_MAX,
    'tipoMax' => ValidadorConfiguracionClinica::TIPO_MAX, 'direccionMax' => ValidadorClinica::DIRECCION_MAX,
    'logoMax' => ValidadorConfiguracionClinica::LOGO_MAX, 'telefonoMin' => ValidadorTelefono::MIN,
    'telefonoMax' => ValidadorTelefono::MAX, 'correoMax' => ValidadorCuenta::CORREO_MAX,
    'enteroMax' => ValidadorConfiguracionClinica::ENTERO_MAX, 'caracteresTexto' => ValidadorClinica::TEXTO_CARACTERES];
$marca = $configuracion['marca'];
?>
<div class="configuracion-clinica" data-configuracion-clinica data-reglas="<?= $e(json_encode($reglas)) ?>" data-tipos="<?= $e(json_encode($configuracion['tipos'])) ?>">
    <section class="horarios-card" aria-labelledby="marcaTitulo">
        <header class="horarios-card__head"><div><h2 id="marcaTitulo">Marca y contacto</h2><p>Datos de esta clínica en el portal, correos y documentos.</p></div></header>
        <div data-vista-marca><?= MarcaClinica::html($marca) ?></div>
        <form data-form-marca novalidate>
            <?php Csrf::field(); ?>
            <input type="hidden" name="id_clinica" value="<?= (int) $marca['id_clinica'] ?>">
            <div class="configuracion-campos">
                <?php foreach (['nombre' => 'Nombre de la clínica', 'direccion' => 'Dirección', 'telefono' => 'Teléfono', 'telefono_urgencias' => 'Teléfono de urgencias', 'email_contacto' => 'Correo de contacto', 'url_logo' => 'URL o ruta del logo'] as $campo => $etiqueta): ?>
                    <label class="configuracion-campo"><?= $e($etiqueta) ?>
                        <input name="<?= $e($campo) ?>" value="<?= $e($marca[$campo] ?? '') ?>" data-config-campo
                            <?php if ($campo === 'nombre' || $campo === 'direccion'): ?>
                                <?= ValidadorClinica::atributosHtml($campo === 'nombre' ? 'nombre_clinica' : 'direccion') ?>
                            <?php elseif (str_starts_with($campo, 'telefono')): ?>
                                <?= ValidadorTelefono::atributosHtml() ?>
                            <?php else: ?>
                                maxlength="<?= $campo === 'url_logo' ? ValidadorConfiguracionClinica::LOGO_MAX : ValidadorCuenta::CORREO_MAX ?>" data-caracteres="\S" inputmode="<?= $campo === 'url_logo' ? 'url' : 'email' ?>"
                            <?php endif; ?>
                            <?= $campo === 'nombre' ? 'required' : '' ?> aria-describedby="marca-error-<?= $e($campo) ?>">
                        <span class="validation-error" id="marca-error-<?= $e($campo) ?>" data-error-campo="<?= $e($campo) ?>" aria-live="polite"></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="configuracion-aviso" data-aviso role="status" aria-live="polite"></p>
            <button class="horarios-btn" type="submit">Guardar datos de la clínica</button>
        </form>
    </section>
    <section class="horarios-card" aria-labelledby="tiposTitulo">
        <header class="horarios-card__head"><div><h2 id="tiposTitulo">Tipos de cita</h2><p>Duración, margen y condición pausable de tus servicios.</p></div><button type="button" class="horarios-btn horarios-btn--sec" data-nuevo-tipo>Nuevo tipo</button></header>
        <div class="configuracion-tipos" data-lista-tipos></div>
        <form data-form-tipo novalidate>
            <?php Csrf::field(); ?>
            <input type="hidden" name="id_clinica" value="<?= (int) $marca['id_clinica'] ?>">
            <input type="hidden" name="id_tipo_cita" value="">
            <h3 data-tipo-titulo>Nuevo tipo de cita</h3>
            <div class="configuracion-campos">
                <label class="configuracion-campo">Nombre
                    <input name="nombre_tipo" required data-config-campo maxlength="<?= ValidadorConfiguracionClinica::TIPO_MAX ?>" data-caracteres="<?= $e(ValidadorClinica::TEXTO_CARACTERES) ?>" data-espacios="normalizar" aria-describedby="tipo-error-nombre_tipo">
                    <span class="validation-error" id="tipo-error-nombre_tipo" data-error-campo="nombre_tipo" aria-live="polite"></span>
                </label>
                <?php foreach (['duracion_minutos' => 'Duración (minutos)', 'margen_minutos' => 'Margen entre citas (minutos)'] as $campo => $etiqueta): ?>
                    <label class="configuracion-campo"><?= $e($etiqueta) ?>
                        <input name="<?= $e($campo) ?>" required data-config-campo inputmode="numeric" maxlength="10" data-caracteres="0-9" aria-describedby="tipo-error-<?= $e($campo) ?>">
                        <span class="validation-error" id="tipo-error-<?= $e($campo) ?>" data-error-campo="<?= $e($campo) ?>" aria-live="polite"></span>
                    </label>
                <?php endforeach; ?>
                <label class="configuracion-campo">¿Se puede pausar ante una urgencia?
                    <select name="pausable" required data-config-campo aria-describedby="tipo-error-pausable"><option value="">Elige una opción</option><option value="1">Sí</option><option value="0">No</option></select>
                    <span class="validation-error" id="tipo-error-pausable" data-error-campo="pausable" aria-live="polite"></span>
                </label>
            </div>
            <p class="configuracion-aviso" data-aviso role="status" aria-live="polite"></p>
            <button class="horarios-btn" type="submit">Guardar tipo de cita</button>
        </form>
    </section>
</div>
<?php require __DIR__ . '/horarios.php'; ?>
<script src="js/configuracion-clinica.js?v=<?= $e(App::assetVersion()) ?>" defer></script>
