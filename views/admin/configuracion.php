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
$camposMarca = ['nombre' => 'Nombre de la clínica', 'telefono' => 'Teléfono', 'telefono_urgencias' => 'Teléfono de urgencias', 'email_contacto' => 'Correo de contacto', 'direccion' => 'Dirección', 'url_logo' => 'URL o ruta del logo'];
?>
<?php // Pulido de interfaz (2026-10-21): la configuración en tres pestañas y los formularios en ventanas, para no apilar todo en un solo scroll. ?>
<?php // Rediseño (2026-10-26): Suscripción es otra pantalla; se entra junto a las pestañas y su botón vuelve aquí. ?>
<div class="config-tabs-fila">
<nav class="config-tabs" role="tablist" aria-label="Secciones de configuración" data-config-tabs>
    <button type="button" role="tab" class="config-tabs__tab" id="config-tab-clinica" aria-controls="config-clinica" aria-selected="true" data-config-tab="clinica"><i class="fas fa-hospital" aria-hidden="true"></i> Clínica</button>
    <button type="button" role="tab" class="config-tabs__tab" id="config-tab-horarios" aria-controls="config-horarios" aria-selected="false" tabindex="-1" data-config-tab="horarios"><i class="fas fa-clock" aria-hidden="true"></i> Horarios</button>
    <button type="button" role="tab" class="config-tabs__tab" id="config-tab-ausencias" aria-controls="config-ausencias" aria-selected="false" tabindex="-1" data-config-tab="ausencias"><i class="fas fa-calendar-times" aria-hidden="true"></i> Ausencias y cierres</button>
</nav>
<a class="config-tabs__enlace" href="index.php?action=admin_suscripcion&amp;desde=configuracion"><i class="fas fa-crown" aria-hidden="true"></i> Suscripción<?php if (!empty($planSuscripcion)): ?> <span class="config-tabs__plan"><?= $e($planSuscripcion) ?></span><?php endif; ?></a>
</div>
<div class="configuracion-clinica" id="config-clinica" role="tabpanel" aria-labelledby="config-tab-clinica" data-config-panel="clinica" data-configuracion-clinica data-reglas="<?= $e(json_encode($reglas)) ?>" data-tipos="<?= $e(json_encode($configuracion['tipos'])) ?>">
    <section class="horarios-card config-marca" aria-labelledby="marcaTitulo">
        <header class="horarios-card__head">
            <div><h2 id="marcaTitulo">Marca y contacto</h2><p>Así aparece la clínica en el portal, los correos y los documentos.</p></div>
            <button type="button" class="coord-btn coord-btn--sec" data-abrir-dialogo="dialogoMarca"><i class="fas fa-pen" aria-hidden="true"></i> Editar</button>
        </header>
        <div class="config-marca__vista" data-vista-marca>
            <?php $inicial = mb_strtoupper(mb_substr((string) ($marca['nombre'] ?? 'Z'), 0, 1)); ?>
            <div class="config-marca__logo" data-marca-logo>
                <?php if (!empty($marca['url_logo'])): ?><img src="<?= $e($marca['url_logo']) ?>" alt="Logo de <?= $e($marca['nombre']) ?>"><?php else: ?><span><?= $e($inicial) ?></span><?php endif; ?>
            </div>
            <dl class="config-marca__datos">
                <?php foreach (['nombre' => 'Clínica', 'telefono' => 'Teléfono', 'telefono_urgencias' => 'Urgencias', 'email_contacto' => 'Correo', 'direccion' => 'Dirección'] as $campo => $etiqueta): ?>
                    <div class="config-marca__dato<?= $campo === 'telefono_urgencias' ? ' config-marca__dato--urgencia' : '' ?>">
                        <dt><?= $e($etiqueta) ?></dt>
                        <dd data-marca-dato="<?= $e($campo) ?>"><?= $e(($marca[$campo] ?? '') !== '' ? $marca[$campo] : 'Sin definir') ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
    </section>
    <section class="horarios-card config-tipos" aria-labelledby="tiposTitulo">
        <header class="horarios-card__head">
            <div><h2 id="tiposTitulo">Tipos de cita</h2><p>Duración, margen entre citas y si se pueden pausar ante una urgencia.</p></div>
            <button type="button" class="coord-btn" data-nuevo-tipo><i class="fas fa-plus" aria-hidden="true"></i> Nuevo tipo</button>
        </header>
        <div class="configuracion-tipos" data-lista-tipos></div>
    </section>

    <dialog class="zk-dialogo" id="dialogoMarca" aria-labelledby="dialogoMarcaTitulo">
        <form data-form-marca novalidate class="zk-dialogo__form">
            <header class="zk-dialogo__head">
                <span class="zk-dialogo__icono" aria-hidden="true"><i class="fas fa-clinic-medical"></i></span>
                <div><h3 id="dialogoMarcaTitulo">Marca y contacto</h3><p>Datos que identifican a tu clínica.</p></div>
                <button type="button" class="zk-dialogo__cerrar" data-cerrar-dialogo aria-label="Cerrar"><i class="fas fa-times" aria-hidden="true"></i></button>
            </header>
            <div class="zk-dialogo__cuerpo">
                <?php Csrf::field(); ?>
                <input type="hidden" name="id_clinica" value="<?= (int) $marca['id_clinica'] ?>">
                <div class="configuracion-campos">
                    <?php foreach ($camposMarca as $campo => $etiqueta): ?>
                        <label class="configuracion-campo<?= in_array($campo, ['direccion', 'url_logo'], true) ? ' configuracion-campo--ancho' : '' ?>"><?= $e($etiqueta) ?>
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
            </div>
            <footer class="zk-dialogo__pie">
                <button type="button" class="coord-btn coord-btn--sec" data-cerrar-dialogo>Cancelar</button>
                <button class="coord-btn" type="submit">Guardar</button>
            </footer>
        </form>
    </dialog>

    <dialog class="zk-dialogo" id="dialogoTipo" aria-labelledby="dialogoTipoTitulo">
        <form data-form-tipo novalidate class="zk-dialogo__form">
            <header class="zk-dialogo__head">
                <span class="zk-dialogo__icono" aria-hidden="true"><i class="fas fa-calendar-check"></i></span>
                <div><h3 id="dialogoTipoTitulo" data-tipo-titulo>Nuevo tipo de cita</h3><p>Define el servicio, su duración y su margen.</p></div>
                <button type="button" class="zk-dialogo__cerrar" data-cerrar-dialogo aria-label="Cerrar"><i class="fas fa-times" aria-hidden="true"></i></button>
            </header>
            <div class="zk-dialogo__cuerpo">
                <?php Csrf::field(); ?>
                <input type="hidden" name="id_clinica" value="<?= (int) $marca['id_clinica'] ?>">
                <input type="hidden" name="id_tipo_cita" value="">
                <div class="configuracion-campos">
                    <label class="configuracion-campo configuracion-campo--ancho">Nombre
                        <input name="nombre_tipo" required data-config-campo maxlength="<?= ValidadorConfiguracionClinica::TIPO_MAX ?>" data-caracteres="<?= $e(ValidadorClinica::TEXTO_CARACTERES) ?>" data-espacios="normalizar" placeholder="Ej. Consulta general" aria-describedby="tipo-error-nombre_tipo">
                        <span class="validation-error" id="tipo-error-nombre_tipo" data-error-campo="nombre_tipo" aria-live="polite"></span>
                    </label>
                    <?php foreach (['duracion_minutos' => 'Duración (minutos)', 'margen_minutos' => 'Margen después (minutos)'] as $campo => $etiqueta): ?>
                        <label class="configuracion-campo"><?= $e($etiqueta) ?>
                            <input name="<?= $e($campo) ?>" required data-config-campo inputmode="numeric" maxlength="10" data-caracteres="0-9" aria-describedby="tipo-error-<?= $e($campo) ?>">
                            <span class="validation-error" id="tipo-error-<?= $e($campo) ?>" data-error-campo="<?= $e($campo) ?>" aria-live="polite"></span>
                        </label>
                    <?php endforeach; ?>
                    <label class="configuracion-campo configuracion-campo--ancho">¿Se puede pausar ante una urgencia?
                        <select name="pausable" required data-config-campo aria-describedby="tipo-error-pausable"><option value="">Elige una opción</option><option value="1">Sí, se puede pausar</option><option value="0">No, no se interrumpe</option></select>
                        <span class="validation-error" id="tipo-error-pausable" data-error-campo="pausable" aria-live="polite"></span>
                    </label>
                </div>
                <p class="configuracion-aviso" data-aviso role="status" aria-live="polite"></p>
            </div>
            <footer class="zk-dialogo__pie">
                <button type="button" class="coord-btn coord-btn--sec" data-cerrar-dialogo>Cancelar</button>
                <button class="coord-btn" type="submit">Guardar</button>
            </footer>
        </form>
    </dialog>
</div>
<div class="config-agenda-hoja">
<div id="config-horarios" role="tabpanel" aria-labelledby="config-tab-horarios" data-config-panel="horarios" hidden>
<?php require __DIR__ . '/horarios.php'; ?>
</div>
<script src="js/configuracion-clinica.js?v=<?= $e(App::assetVersion()) ?>" defer></script>
<script src="js/dialogos-zooki.js?v=<?= $e(App::assetVersion()) ?>" defer></script>

<?php require __DIR__ . '/../partials/coordinacion_horarios.php'; ?>
</div>
