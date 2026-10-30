<?php
require_once __DIR__ . '/../../helpers/ValidadorConfiguracionClinica.php';
require_once __DIR__ . '/../../helpers/Csrf.php';
require_once __DIR__ . '/../../models/MarcaClinica.php';
require_once __DIR__ . '/../../helpers/LogoClinica.php';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$reglas = ['nombreMin' => ValidadorClinica::NOMBRE_MIN, 'nombreMax' => ValidadorClinica::NOMBRE_MAX,
    'tipoMax' => ValidadorConfiguracionClinica::TIPO_MAX, 'direccionMax' => ValidadorClinica::DIRECCION_MAX,
    'logoMax' => ValidadorConfiguracionClinica::LOGO_MAX, 'telefonoMin' => ValidadorTelefono::MIN,
    'telefonoMax' => ValidadorTelefono::MAX, 'correoMax' => ValidadorCuenta::CORREO_MAX,
    'duracionMax' => ValidadorConfiguracionClinica::DURACION_MAX, 'margenMax' => ValidadorConfiguracionClinica::MARGEN_MAX,
    'cambiosMax' => ReglaCambioCita::MAX,
    'logoMaxBytes' => LogoClinica::MAX_BYTES, 'caracteresTexto' => ValidadorClinica::TEXTO_CARACTERES];
$marca = $configuracion['marca'];
$partesNombre = array_values(array_filter(explode(' ', trim($perfil['nombre_completo'] ?? ''))));
$iniciales = mb_strtoupper(mb_substr($partesNombre[0] ?? '', 0, 1) . mb_substr($partesNombre[1] ?? '', 0, 1)) ?: 'A';
$esVetPerfil = false;
$camposMarca = ['nombre' => 'Nombre de la clínica', 'telefono' => 'Teléfono', 'telefono_urgencias' => 'Teléfono de urgencias', 'email_contacto' => 'Correo de contacto', 'direccion' => 'Dirección'];
$ejemplosMarca = ['nombre' => 'Ej. Clínica Veterinaria Norte', 'telefono' => 'Ej. 300 123 4567',
    'telefono_urgencias' => 'Ej. 310 987 6543', 'email_contacto' => 'Ej. contacto@tuclinica.com',
    'direccion' => 'Ej. Calle 10 # 5-20', 'url_logo' => 'Ej. https://tuclinica.com/logo.png'];
?>
<?php // Pulido de interfaz (2026-10-21): la configuración en pestañas y los formularios en ventanas, para no apilar todo en un solo scroll. ?>
<?php // Rediseño (2026-10-26): «Ajustes» reúne Configuración y Mi perfil del administrador en una hoja con menú lateral blanco. ?>
<?php // En el celular el menú es una lista agrupada y cada sección se abre con «← Ajustes» (public/js/ajustes.js). ?>
<div class="perfil-secciones ajustes" data-perfil-secciones data-ajustes data-ajustes-origen="<?= $e($origenAjustes ?? '') ?>" data-ajustes-seccion-inicial="<?= $e($seccionInicialAjustes ?? '') ?>">
    <aside class="perfil-lateral ajustes-lateral">
        <?php
        $nombreClinicaPerfil = $marca['nombre'];
        require __DIR__ . '/../perfil/identidad.php';
        ?>
        <nav class="ajustes-menu" data-config-tabs aria-label="Secciones de Ajustes" aria-orientation="vertical">
            <p class="ajustes-menu__grupo" id="ajustesGrupoClinica">La clínica</p>
            <div class="ajustes-menu__caja">
            <div class="ajustes-menu__lista" role="tablist" aria-labelledby="ajustesGrupoClinica" aria-orientation="vertical">
                <button type="button" role="tab" class="ajustes-menu__item" id="config-tab-clinica" aria-controls="config-clinica" aria-selected="true" data-config-tab="clinica"><i class="fas fa-hospital" aria-hidden="true"></i><span>Datos de la clínica</span><i class="fas fa-chevron-right ajustes-menu__flecha" aria-hidden="true"></i></button>
                <button type="button" role="tab" class="ajustes-menu__item" id="config-tab-horarios" aria-controls="config-horarios" aria-selected="false" tabindex="-1" data-config-tab="horarios"><i class="fas fa-clock" aria-hidden="true"></i><span>Horarios</span><i class="fas fa-chevron-right ajustes-menu__flecha" aria-hidden="true"></i></button>
                <button type="button" role="tab" class="ajustes-menu__item" id="config-tab-ausencias" aria-controls="config-ausencias" aria-selected="false" tabindex="-1" data-config-tab="ausencias"><i class="fas fa-calendar-times" aria-hidden="true"></i><span>Ausencias y cierres</span><i class="fas fa-chevron-right ajustes-menu__flecha" aria-hidden="true"></i></button>
                <button type="button" role="tab" class="ajustes-menu__item" id="config-tab-suscripcion" aria-controls="config-suscripcion" aria-selected="false" tabindex="-1" data-config-tab="suscripcion" data-ajustes-etiqueta="Suscripción"><i class="fas fa-crown" aria-hidden="true"></i><span>Suscripción</span><?php if (!empty($planSuscripcion)): ?><span class="ajustes-menu__plan"><?= $e($planSuscripcion) ?></span><?php endif; ?><i class="fas fa-chevron-right ajustes-menu__flecha" aria-hidden="true"></i></button>
            </div>
            </div>
            <?php require __DIR__ . '/../perfil/menu_cuenta.php'; ?>
        </nav>
        <a href="index.php?action=logout" class="ajustes-salir"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Cerrar sesión</a>
    </aside>
    <div class="perfil-contenido ajustes-contenido">
        <div class="ajustes-seccion">
            <button type="button" class="ajustes-seccion__volver" data-ajustes-volver aria-label="Volver a Ajustes"><i class="fas fa-arrow-left" aria-hidden="true"></i><span>Ajustes</span></button>
            <a class="ajustes-seccion__volver" href="index.php?action=admin_panel" data-ajustes-volver-inicio aria-label="Volver a Inicio" hidden><i class="fas fa-arrow-left" aria-hidden="true"></i><span>Inicio</span></a>
            <h2 class="ajustes-seccion__titulo" data-ajustes-titulo>Datos de la clínica</h2>
        </div>
<div class="configuracion-clinica" id="config-clinica" role="tabpanel" aria-labelledby="config-tab-clinica" data-config-panel="clinica" data-configuracion-clinica data-reglas="<?= $e(json_encode($reglas)) ?>" data-tipos="<?= $e(json_encode($configuracion['tipos'])) ?>">
    <section class="horarios-card config-marca" aria-labelledby="marcaTitulo">
        <header class="horarios-card__head">
            <div><h2 id="marcaTitulo">Marca y contacto</h2></div>
            <button type="button" class="coord-btn coord-btn--sec" data-abrir-dialogo="dialogoMarca"><i class="fas fa-pen" aria-hidden="true"></i> Editar</button>
        </header>
        <div class="config-marca__vista" data-vista-marca>
            <?php $inicial = mb_strtoupper(mb_substr((string) ($marca['nombre'] ?? 'Z'), 0, 1)); ?>
            <div class="config-marca__identidad">
            <div class="config-marca__logo" data-marca-logo>
                <?php if (!empty($marca['url_logo'])): ?><img src="<?= $e($marca['url_logo']) ?>" alt="Logo de <?= $e($marca['nombre']) ?>"><?php else: ?><span><?= $e($inicial) ?></span><?php endif; ?>
            </div>
            <div><p class="config-marca__etiqueta">Nombre de la clínica</p><strong class="config-marca__nombre" data-marca-dato="nombre"><?= $e($marca['nombre']) ?></strong></div>
            </div>
            <dl class="config-marca__datos">
                <?php foreach (['telefono' => ['Teléfono', 'fa-phone'], 'telefono_urgencias' => ['Urgencias', 'fa-phone-volume'], 'email_contacto' => ['Correo', 'fa-envelope'], 'direccion' => ['Dirección', 'fa-map-marker-alt']] as $campo => [$etiqueta, $icono]): ?>
                    <div class="config-marca__dato">
                        <dt><i class="fas <?= $e($icono) ?>" aria-hidden="true"></i><?= $e($etiqueta) ?></dt>
                        <dd data-marca-dato="<?= $e($campo) ?>"><?= $e(($marca[$campo] ?? '') ?: 'Sin registrar') ?></dd>
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
        <form data-form-marca novalidate class="zk-dialogo__form" enctype="multipart/form-data">
            <header class="zk-dialogo__head">
                <span class="zk-dialogo__icono" aria-hidden="true"><i class="fas fa-clinic-medical"></i></span>
                <div><h3 id="dialogoMarcaTitulo">Marca y contacto</h3><p>Datos que identifican a tu clínica.</p></div>
                <button type="button" class="zk-dialogo__cerrar" data-cerrar-dialogo aria-label="Cerrar"><i class="fas fa-times" aria-hidden="true"></i></button>
            </header>
            <div class="zk-dialogo__cuerpo">
                <?php Csrf::field(); ?>
                <input type="hidden" name="id_clinica" value="<?= (int) $marca['id_clinica'] ?>">
                <input type="hidden" name="url_logo" value="<?= $e($marca['url_logo'] ?? '') ?>" data-config-campo>
                <div class="configuracion-campos">
                    <?php foreach ($camposMarca as $campo => $etiqueta): ?>
                        <label class="configuracion-campo<?= in_array($campo, ['direccion', 'url_logo'], true) ? ' configuracion-campo--ancho' : '' ?>"><?= $e($etiqueta) ?>
                            <input name="<?= $e($campo) ?>" value="<?= $e($marca[$campo] ?? '') ?>" placeholder="<?= $e($ejemplosMarca[$campo]) ?>" data-config-campo
                                <?php if ($campo === 'nombre' || $campo === 'direccion'): ?>
                                    <?= ValidadorClinica::atributosHtml($campo === 'nombre' ? 'nombre_clinica' : 'direccion') ?>
                                <?php elseif (str_starts_with($campo, 'telefono')): ?>
                                    type="tel" autocomplete="tel" data-telefono-internacional data-telefono-prefijo-separado="1" <?= ValidadorTelefono::atributosHtml() ?>
                                <?php else: ?>
                                    maxlength="<?= $campo === 'url_logo' ? ValidadorConfiguracionClinica::LOGO_MAX : ValidadorCuenta::CORREO_MAX ?>" data-caracteres="\S" inputmode="<?= $campo === 'url_logo' ? 'url' : 'email' ?>"
                                <?php endif; ?>
                                <?= $campo === 'nombre' ? 'required' : '' ?> aria-describedby="marca-error-<?= $e($campo) ?>">
                            <span class="validation-error" id="marca-error-<?= $e($campo) ?>" data-error-campo="<?= $e($campo) ?>" aria-live="polite"></span>
                        </label>
                    <?php endforeach; ?>
                    <div class="config-logo">
                        <label for="marcaLogoArchivo">Logo de la clínica <span>(opcional)</span></label>
                        <input type="file" id="marcaLogoArchivo" name="logo_archivo" accept="image/jpeg,image/png,image/webp" data-logo-archivo aria-describedby="marcaLogoAyuda marca-error-logo_archivo" hidden>
                        <button type="button" class="config-logo__soltar" data-logo-soltar>
                            <span class="config-logo__vista"><img data-logo-vista alt="Vista previa del logo" hidden><i class="fas fa-cloud-upload-alt" data-logo-icono aria-hidden="true"></i></span>
                            <span><strong>Arrastra una imagen o elige un archivo</strong><span id="marcaLogoAyuda">JPG, PNG o WebP · hasta 5 MB</span></span>
                        </button>
                        <button type="button" class="config-logo__cancelar" data-logo-cancelar hidden>Cancelar cambio del logo</button>
                        <span class="validation-error" id="marca-error-logo_archivo" data-error-campo="logo_archivo" aria-live="polite"></span>
                    </div>
                </div>
                <label class="configuracion-campo">Anticipación para cambiar citas (minutos)
                    <input name="anticipacion_cambios_min" value="<?= (int) ($marca['anticipacion_cambios_min'] ?? ReglaCambioCita::DEFECTO) ?>" placeholder="Ej. 30" data-config-campo required inputmode="numeric" maxlength="4" data-caracteres="0-9" data-maximo="<?= ReglaCambioCita::MAX ?>" aria-describedby="marca-ayuda-anticipacion_cambios_min marca-error-anticipacion_cambios_min">
                    <span class="configuracion-ayuda" id="marca-ayuda-anticipacion_cambios_min">De 0 a <?= ReglaCambioCita::MAX ?> minutos. 0 desactiva la restricción. No limita urgencias ni decisiones clínicas.</span>
                    <span class="validation-error" id="marca-error-anticipacion_cambios_min" data-error-campo="anticipacion_cambios_min" aria-live="polite"></span>
                </label>
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
                            <?php $topeMinutos = $campo === 'duracion_minutos' ? ValidadorConfiguracionClinica::DURACION_MAX : ValidadorConfiguracionClinica::MARGEN_MAX; ?>
                            <input name="<?= $e($campo) ?>" required data-config-campo inputmode="numeric" maxlength="3" data-caracteres="0-9" data-formato="decimal" data-enteros="3" data-decimales="0" data-maximo="<?= $topeMinutos ?>" placeholder="<?= $campo === 'duracion_minutos' ? 'Ej. 30' : 'Ej. 10' ?>" aria-describedby="tipo-ayuda-<?= $e($campo) ?> tipo-error-<?= $e($campo) ?>">
                            <span class="configuracion-ayuda" id="tipo-ayuda-<?= $e($campo) ?>"><?= $campo === 'duracion_minutos' ? 'De 1 a ' . $topeMinutos . ' min (12 horas).' : 'De 0 a ' . $topeMinutos . ' min entre citas.' ?></span>
                            <span class="validation-error" id="tipo-error-<?= $e($campo) ?>" data-error-campo="<?= $e($campo) ?>" aria-live="polite"></span>
                        </label>
                    <?php endforeach; ?>
                    <label class="configuracion-campo configuracion-campo--ancho">¿Se puede pausar ante una urgencia?
                        <select name="pausable" required data-config-campo aria-describedby="tipo-error-pausable"><option value="">Elige una opción</option><option value="1">Sí, se puede pausar</option><option value="0">No, no se interrumpe</option></select>
                        <span class="validation-error" id="tipo-error-pausable" data-error-campo="pausable" aria-live="polite"></span>
                    </label>
                    <label class="configuracion-campo configuracion-campo--ancho">¿Solo puede reservarlo el personal?
                        <select name="solo_personal" required data-config-campo aria-describedby="tipo-error-solo_personal"><option value="0">No, también se ofrece en el portal</option><option value="1">Sí, solo el personal</option></select>
                        <span class="validation-error" id="tipo-error-solo_personal" data-error-campo="solo_personal" aria-live="polite"></span>
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
<div class="config-agenda-hoja" data-ajustes-horarios>
<div id="config-horarios" role="tabpanel" aria-labelledby="config-tab-horarios" data-config-panel="horarios" hidden>
<?php require __DIR__ . '/horarios.php'; ?>
</div>
<script src="js/logo-clinica.js?v=<?= $e(App::assetVersion()) ?>" defer></script>
<script src="js/configuracion-clinica.js?v=<?= $e(App::assetVersion()) ?>" defer></script>
<script src="js/dialogos-zooki.js?v=<?= $e(App::assetVersion()) ?>" defer></script>
<?php require __DIR__ . '/../partials/coordinacion_horarios.php'; ?>
<script src="js/ajustes-horarios.js?v=<?= $e(App::assetVersion()) ?>" defer></script>
</div>
        <div class="perfil ajustes-cuenta">
            <?php require __DIR__ . '/../perfil/paneles_cuenta.php'; ?>
        </div>
        <div id="config-suscripcion" role="tabpanel" aria-labelledby="config-tab-suscripcion" data-config-panel="suscripcion" hidden>
            <?php
            ['estadoSuscripcion' => $estadoSuscripcion, 'profesionalSuscripcion' => $profesionalSuscripcion,
                'pagoDisponible' => $pagoDisponible, 'mensajePago' => $mensajePago, 'historialPagos' => $historialPagos,
                'fuentePago' => $fuentePago, 'automaticoDisponible' => $automaticoDisponible, 'mensajeAutomatico' => $mensajeAutomatico,
                'requiereAceptacion' => $requiereAceptacion, 'retornoPago' => $retornoPago, 'origenSuscripcion' => $origenSuscripcion] = $datosSuscripcion;
            require __DIR__ . '/suscripcion.php';
            ?>
        </div>
    </div>
</div>
<?php // Sin defer: ajustes.js lee la sección pedida en la dirección antes de que dialogos-zooki.js (diferido) escriba la primera. ?>
<script src="js/ajustes.js?v=<?= $e(App::assetVersion()) ?>"></script>
<script src="js/perfil.js?v=<?= $e(App::assetVersion()) ?>" defer></script>
