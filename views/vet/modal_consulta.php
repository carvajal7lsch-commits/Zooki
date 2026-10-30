<!-- MODAL NUEVA CONSULTA -->
<?php
// M2 C5a: los límites y avisos son los de ValidadorClinico; el comportamiento
// está en js/consulta-formulario.js (sin eventos en línea).
// M2 C8 (H5, H9): la identidad de Zooki (css/consulta-clinica.css), secciones
// claras, pie siempre a la vista y los mismos campos que Atención.
require_once __DIR__ . '/../../helpers/ValidadorClinico.php';
require_once __DIR__ . '/../../helpers/ValidadorTratamiento.php';
$avisoConsulta = fn(string $campo): string => htmlspecialchars(ValidadorClinico::AVISOS_CONSULTA[$campo], ENT_QUOTES, 'UTF-8');
$ayudaSigno = fn(string $campo): string => htmlspecialchars(ValidadorClinico::ayudaSigno($campo), ENT_QUOTES, 'UTF-8');
$claseCampo = 'consulta-campo';
$claseEtiqueta = 'consulta-campo__etiqueta';
$prefijoConsulta = 'modalConsulta';
?>
<div id="modalConsulta" class="users-modal consulta-modal" data-paciente-compacto data-ui-accion="cerrar-consulta" data-ui-fondo>
    <div class="modal-content users-modal__panel modal-lg consulta-modal__panel" role="dialog" aria-modal="true" aria-labelledby="consultaModalTitulo">
        <div class="modal-header consulta-modal__cabecera">
            <div class="consulta-modal__paciente">
                <span class="avatar-iniciales consulta-avatar" id="consultationPetInicial" aria-hidden="true"></span>
                <div>
                    <p class="consulta-modal__sobretitulo">Nueva consulta</p>
                    <h3 class="consulta-modal__titulo" id="consultaModalTitulo"><span id="consultationPetName"></span></h3>
                    <p class="paciente-compacto__especie" id="consultaPacienteEspecie"></p>
                </div>
            </div>
            <button type="button" class="close-modal" data-ui-accion="cerrar-consulta" aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="consulta-modal__ficha">
            <p class="paciente-compacto__datos" id="consultaPacienteDatos"></p>
            <div class="paciente-compacto__controles">
                <button type="button" class="paciente-compacto__alertas" data-paciente-alertas aria-expanded="false" aria-controls="consultaAlertas">Cargando alertas</button>
                <button type="button" class="paciente-compacto__ficha" data-paciente-ficha aria-expanded="false" aria-controls="consultaFicha">Ver ficha <i class="fas fa-chevron-down" aria-hidden="true"></i></button>
            </div>
            <p class="paciente-compacto__ficha-contenido" id="consultaFicha"></p>
            <div class="paciente-compacto__lista" id="consultaAlertas">

            </div>
        </div>
        <div class="modal-tabs consulta-modal__pestanas">
            <button type="button" class="modal-tab-btn active" data-ui-accion="pestana-consulta" data-tab="tabMotivo">
                <i class="fas fa-comment-medical" aria-hidden="true"></i> Motivo
            </button>
            <button type="button" class="modal-tab-btn" data-ui-accion="pestana-consulta" data-tab="tabSignos">
                <i class="fas fa-heartbeat" aria-hidden="true"></i> Signos
            </button>
            <button type="button" class="modal-tab-btn" data-ui-accion="pestana-consulta" data-tab="tabResolucion">
                <i class="fas fa-clipboard-check" aria-hidden="true"></i> Plan
            </button>
            <button type="button" class="modal-tab-btn" data-ui-accion="pestana-consulta" data-tab="tabArchivos">
                <i class="fas fa-paperclip" aria-hidden="true"></i> Archivos
            </button>
        </div>

        <form id="formConsulta" class="consulta-formulario" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="id_mascota" id="consultation_id_mascota">

            <div class="users-modal__body consulta-modal__cuerpo">
                <div id="tabMotivo" class="modal-tab-content active">
                    <div class="consulta-campo">
                        <label for="consultaMotivo" class="consulta-campo__etiqueta">Motivo de la consulta <span class="consulta-obligatorio" aria-hidden="true">*</span></label>
                        <input type="text" id="consultaMotivo" name="motivo" required aria-required="true" maxlength="<?= ValidadorClinico::TEXTO_MAX ?>"
                               data-consulta-campo="motivo" data-aviso-requerido="<?= $avisoConsulta('motivo') ?>" placeholder="Por qué viene hoy">
                        <small class="cuenta-validacion" data-campo-aviso="motivo"></small>
                    </div>
                    <?php include __DIR__ . '/../partials/consulta_sintomas.php'; ?>
                    <div class="consulta-campo">
                        <label for="consultaAnamnesis" class="consulta-campo__etiqueta">Anamnesis</label>
                        <textarea id="consultaAnamnesis" name="anamnesis" rows="4" maxlength="<?= ValidadorClinico::TEXTO_MAX ?>" placeholder="Historia relatada por el propietario, desde cuándo…"></textarea>
                    </div>
                </div>

                <div id="tabSignos" class="modal-tab-content">
                    <?php // M2 C7 (hallazgo de A.1): frecuencia respiratoria, como en Atención; C8 (H8): restricción de entrada de ValidadorClinico. ?>
                    <div class="consulta-signos">
                        <div class="consulta-signo">
                            <span class="consulta-signo__icono" aria-hidden="true"><i class="fas fa-weight"></i></span>
                            <div class="consulta-signo__cuerpo">
                                <label for="consultaPeso" class="consulta-campo__etiqueta">Peso <span class="consulta-obligatorio" aria-hidden="true">*</span></label>
                                <div class="consulta-signo__entrada">
                                    <input id="consultaPeso" name="peso" <?= ValidadorClinico::atributosSigno('peso') ?> required aria-required="true"
                                           data-consulta-campo="peso" data-aviso-rango="<?= $avisoConsulta('peso') ?>" data-aviso-requerido="<?= htmlspecialchars(ValidadorClinico::AVISO_PESO_REQUERIDO, ENT_QUOTES, 'UTF-8') ?>" placeholder="0.00">
                                    <span class="consulta-signo__unidad">kg</span>
                                </div>
                                <small class="consulta-ayuda"><?= $ayudaSigno('peso') ?></small>
                                <?php // Decisión del usuario (2026-10-15): sin peso aproximado; la casilla deja el peso en NULL. ?>
                                <label class="consulta-sin-peso"><input type="checkbox" name="peso_desconocido" value="1" data-sin-peso="peso"> <span>No se pudo pesar</span></label>
                                <small class="cuenta-validacion" data-campo-aviso="peso"></small>
                            </div>
                        </div>
                        <div class="consulta-signo">
                            <span class="consulta-signo__icono" aria-hidden="true"><i class="fas fa-thermometer-half"></i></span>
                            <div class="consulta-signo__cuerpo">
                                <label for="consultaTemperatura" class="consulta-campo__etiqueta">Temperatura</label>
                                <div class="consulta-signo__entrada">
                                    <input id="consultaTemperatura" <?= ValidadorClinico::atributosSigno('temperatura') ?> name="temperatura" data-consulta-campo="temperatura" data-aviso-rango="<?= $avisoConsulta('temperatura') ?>" placeholder="38.5">
                                    <span class="consulta-signo__unidad">°C</span>
                                </div>
                                <small class="consulta-ayuda"><?= $ayudaSigno('temperatura') ?></small>
                                <small class="cuenta-validacion" data-campo-aviso="temperatura"></small>
                            </div>
                        </div>
                        <div class="consulta-signo">
                            <span class="consulta-signo__icono" aria-hidden="true"><i class="fas fa-heartbeat"></i></span>
                            <div class="consulta-signo__cuerpo">
                                <label for="consultaFrecuenciaCardiaca" class="consulta-campo__etiqueta">Frecuencia cardíaca</label>
                                <div class="consulta-signo__entrada">
                                    <input id="consultaFrecuenciaCardiaca" <?= ValidadorClinico::atributosSigno('frecuencia_cardiaca') ?> name="frecuencia_cardiaca" data-consulta-campo="frecuencia_cardiaca" data-aviso-rango="<?= $avisoConsulta('frecuencia_cardiaca') ?>" placeholder="100">
                                    <span class="consulta-signo__unidad">lpm</span>
                                </div>
                                <small class="consulta-ayuda"><?= $ayudaSigno('frecuencia_cardiaca') ?></small>
                                <small class="cuenta-validacion" data-campo-aviso="frecuencia_cardiaca"></small>
                            </div>
                        </div>
                        <div class="consulta-signo">
                            <span class="consulta-signo__icono" aria-hidden="true"><i class="fas fa-lungs"></i></span>
                            <div class="consulta-signo__cuerpo">
                                <label for="consultaFrecuenciaRespiratoria" class="consulta-campo__etiqueta">Frecuencia respiratoria</label>
                                <div class="consulta-signo__entrada">
                                    <input id="consultaFrecuenciaRespiratoria" <?= ValidadorClinico::atributosSigno('frecuencia_respiratoria') ?> name="frecuencia_respiratoria" data-consulta-campo="frecuencia_respiratoria" data-aviso-rango="<?= $avisoConsulta('frecuencia_respiratoria') ?>" placeholder="24">
                                    <span class="consulta-signo__unidad">rpm</span>
                                </div>
                                <small class="consulta-ayuda"><?= $ayudaSigno('frecuencia_respiratoria') ?></small>
                                <small class="cuenta-validacion" data-campo-aviso="frecuencia_respiratoria"></small>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="tabResolucion" class="modal-tab-content">
                    <div id="consultaAlertasEscritorio"><section class="alertas-medicas alertas-medicas--compacta" aria-label="Alergias y alertas" data-alertas-medicas data-alertas-consulta hidden></section></div>
                    <?php // M2 C5b: alergias y alertas de la mascota a la vista antes de prescribir (C1). ?>
                    <section class="consulta-seccion" aria-labelledby="consultaSeccionDiagnostico">
                        <h4 class="consulta-seccion__titulo" id="consultaSeccionDiagnostico">Diagnóstico</h4>
                        <?php include __DIR__ . '/../partials/consulta_diagnosticos.php'; ?>
                        <div class="consulta-campo">
                            <label for="consultaDiagnostico" class="consulta-campo__etiqueta">Diagnóstico <span class="consulta-obligatorio" aria-hidden="true">*</span></label>
                            <textarea id="consultaDiagnostico" name="diagnostico" rows="3" required aria-required="true" maxlength="<?= ValidadorClinico::TEXTO_MAX ?>"
                                      data-consulta-campo="diagnostico" data-aviso-requerido="<?= $avisoConsulta('diagnostico') ?>"></textarea>
                            <small class="cuenta-validacion" data-campo-aviso="diagnostico"></small>
                        </div>
                        <?php // M2 C7 (hallazgo de A.1): observaciones, como en Atención; el servidor ya las admite. ?>
                        <div class="consulta-fila-2">
                            <div class="consulta-campo">
                                <label for="consultaPlan" class="consulta-campo__etiqueta">Plan / recomendaciones</label>
                                <textarea id="consultaPlan" name="plan_tratamiento" data-consulta-campo="plan_tratamiento" rows="2" maxlength="<?= ValidadorClinico::TEXTO_MAX ?>"></textarea>
                            </div>
                            <div class="consulta-campo">
                                <label for="consultaObservaciones" class="consulta-campo__etiqueta">Observaciones</label>
                                <textarea id="consultaObservaciones" name="observaciones" rows="2" maxlength="<?= ValidadorClinico::TEXTO_MAX ?>"></textarea>
                            </div>
                        </div>
                    </section>
                    <section class="consulta-seccion consulta-tratamientos" aria-labelledby="consultaSeccionTratamientos" data-tratamientos data-avisos="<?= htmlspecialchars(json_encode(ValidadorTratamiento::AVISOS, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="consulta-seccion__cabecera">
                            <h4 class="consulta-seccion__titulo" id="consultaSeccionTratamientos">Tratamientos</h4>
                            <button type="button" class="consulta-boton consulta-boton--suave" data-agregar-tratamiento><i class="fas fa-plus" aria-hidden="true"></i> Agregar medicamento</button>
                        </div>
                        <p class="consulta-tratamientos__vacio" data-tratamientos-vacio>Sin medicamentos. Agrega uno si hace falta.</p>
                        <div id="treatmentsList" data-tratamientos-lista></div>
                        <?php include __DIR__ . '/../partials/consulta_tratamiento_fila.php'; ?>
                    </section>
                </div>

                <div id="tabArchivos" class="modal-tab-content">
                    <label class="consulta-adjuntos file-upload-zone">
                        <input type="file" name="archivos[]" multiple accept=".jpg,.jpeg,.png,.pdf" data-consulta-archivos>
                        <i class="fas fa-cloud-upload-alt" aria-hidden="true"></i>
                        <span>Arrastra o elige radiografías, exámenes o fotos</span>
                        <small class="consulta-ayuda">JPG, PNG o PDF · máximo 10 MB cada uno</small>
                        <div id="fileList" class="file-list"></div>
                    </label>
                </div>
            </div>

            <div class="users-modal__footer consulta-modal__pie">
                <button type="button" class="btn-modal-secondary" data-ui-accion="cerrar-consulta">Cancelar</button>
                <button type="submit" class="btn-modal-primary"><i class="fas fa-floppy-disk" aria-hidden="true"></i> Guardar consulta</button>
            </div>
        </form>
    </div>
</div>
