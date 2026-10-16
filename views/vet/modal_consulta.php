<!-- MODAL NUEVA CONSULTA -->
<?php
// M2 C5a: los límites y avisos son los de ValidadorClinico; el comportamiento
// está en js/consulta-formulario.js (sin eventos en línea).
require_once __DIR__ . '/../../helpers/ValidadorClinico.php';
require_once __DIR__ . '/../../helpers/ValidadorTratamiento.php';
$avisoConsulta = fn(string $campo): string => htmlspecialchars(ValidadorClinico::AVISOS_CONSULTA[$campo], ENT_QUOTES, 'UTF-8');
$claseCampo = 'input-group full';
$claseEtiqueta = '';
$prefijoConsulta = 'modalConsulta';
?>
<div id="modalConsulta" class="users-modal" data-ui-accion="cerrar-consulta" data-ui-fondo>
    <div class="modal-content users-modal__panel modal-lg">
        <div class="modal-header">
            <h3 class="modal-header-title">
                <i class="fas fa-stethoscope icon-primary"></i>
                Atención Médica: <span id="consultationPetName"></span>
            </h3>
            <button type="button" class="close-modal" data-ui-accion="cerrar-consulta" aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-tabs">
            <button class="modal-tab-btn active" data-ui-accion="pestana-consulta" data-tab="tabMotivo">
                <i class="fas fa-comment-medical"></i> Motivo
            </button>
            <button class="modal-tab-btn" data-ui-accion="pestana-consulta" data-tab="tabSignos">
                <i class="fas fa-heartbeat"></i> Signos
            </button>
            <button class="modal-tab-btn" data-ui-accion="pestana-consulta" data-tab="tabResolucion">
                <i class="fas fa-clipboard-check"></i> Plan
            </button>
            <button class="modal-tab-btn" data-ui-accion="pestana-consulta" data-tab="tabArchivos">
                <i class="fas fa-paperclip"></i> Archivos
            </button>
        </div>

        <form id="formConsulta" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="id_mascota" id="consultation_id_mascota">
            
            <div class="users-modal__body">
                <div id="tabMotivo" class="modal-tab-content active">
                    <div class="input-group full">
                        <label for="consultaMotivo">Motivo de Consulta *</label>
                        <input type="text" id="consultaMotivo" name="motivo" required aria-required="true" maxlength="<?= ValidadorClinico::TEXTO_MAX ?>"
                               data-consulta-campo="motivo" data-aviso-requerido="<?= $avisoConsulta('motivo') ?>">
                        <small class="cuenta-validacion" data-campo-aviso="motivo">Obligatorio.</small>
                    </div>
                    <?php include __DIR__ . '/../partials/consulta_sintomas.php'; ?>
                    <div class="input-group full">
                        <label>Anamnesis</label>
                        <textarea name="anamnesis" rows="4" maxlength="<?= ValidadorClinico::TEXTO_MAX ?>"></textarea>
                    </div>
                </div>

                <div id="tabSignos" class="modal-tab-content">
                    <div class="vitals-dashboard-grid">
                        <div class="vital-card">
                            <div class="vital-sign-icon weight">
                                <i class="fas fa-weight"></i>
                            </div>
                            <div class="vital-input-col">
                                <label for="consultaPeso">Peso (Kg) *</label>
                                <input type="number" id="consultaPeso" step="0.01" min="<?= ValidadorClinico::PESO_MIN ?>" max="<?= ValidadorClinico::PESO_MAX ?>" name="peso" required aria-required="true"
                                       data-consulta-campo="peso" data-aviso-rango="<?= $avisoConsulta('peso') ?>" data-aviso-requerido="<?= htmlspecialchars(ValidadorClinico::AVISO_PESO_REQUERIDO, ENT_QUOTES, 'UTF-8') ?>">
                                <?php // Decisión del usuario (2026-10-15): sin peso aproximado; la casilla deja el peso en NULL. ?>
                                <label class="consulta-sin-peso"><input type="checkbox" name="peso_desconocido" value="1" data-sin-peso="peso"> No se pudo pesar</label>
                                <small class="cuenta-validacion" data-campo-aviso="peso">Obligatorio.</small>
                            </div>
                        </div>
                        <div class="vital-card">
                            <div class="vital-sign-icon temp">
                                <i class="fas fa-thermometer-half"></i>
                            </div>
                            <div class="vital-input-col">
                                <label>Temp (°C)</label>
                                <input type="number" step="0.1" min="<?= ValidadorClinico::TEMP_MIN ?>" max="<?= ValidadorClinico::TEMP_MAX ?>" name="temperatura" data-consulta-campo="temperatura" data-aviso-rango="<?= $avisoConsulta('temperatura') ?>">
                            </div>
                        </div>
                        <div class="vital-card">
                            <div class="vital-sign-icon heart">
                                <i class="fas fa-heartbeat"></i>
                            </div>
                            <div class="vital-input-col">
                                <label>F.C. (LPM)</label>
                                <input type="number" step="1" min="<?= ValidadorClinico::FC_MIN ?>" max="<?= ValidadorClinico::FC_MAX ?>" name="frecuencia_cardiaca" data-consulta-campo="frecuencia_cardiaca" data-aviso-rango="<?= $avisoConsulta('frecuencia_cardiaca') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div id="tabResolucion" class="modal-tab-content">
                    <?php // M2 C5b: alergias y alertas de la mascota a la vista antes de prescribir (C1). ?>
                    <section class="alertas-medicas alertas-medicas--compacta" aria-label="Alergias y alertas" data-alertas-medicas data-alertas-consulta hidden></section>
                    <?php include __DIR__ . '/../partials/consulta_diagnosticos.php'; ?>
                    <div class="input-group full">
                        <label for="consultaDiagnostico">Diagnóstico *</label>
                        <textarea id="consultaDiagnostico" name="diagnostico" rows="3" required aria-required="true" maxlength="<?= ValidadorClinico::TEXTO_MAX ?>"
                                  data-consulta-campo="diagnostico" data-aviso-requerido="<?= $avisoConsulta('diagnostico') ?>"></textarea>
                        <small class="cuenta-validacion" data-campo-aviso="diagnostico">Obligatorio.</small>
                    </div>
                    <div class="input-group full">
                        <label>Plan / Recomendaciones</label>
                        <textarea name="plan_tratamiento" rows="2" maxlength="<?= ValidadorClinico::TEXTO_MAX ?>"></textarea>
                    </div>
                    <div class="treatments-section" data-tratamientos data-avisos="<?= htmlspecialchars(json_encode(ValidadorTratamiento::AVISOS, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="section-title-action">
                            <label>Tratamientos</label>
                            <button type="button" class="btn-add-treatment" data-agregar-tratamiento><i class="fas fa-plus"></i> Agregar</button>
                        </div>
                        <div id="treatmentsList" data-tratamientos-lista></div>
                        <?php include __DIR__ . '/../partials/consulta_tratamiento_fila.php'; ?>
                    </div>
                </div>
                
                <div id="tabArchivos" class="modal-tab-content">
                    <div class="file-upload-zone">
                        <input type="file" name="archivos[]" multiple accept=".jpg,.jpeg,.png,.pdf" data-consulta-archivos>
                        <i class="fas fa-cloud-upload-alt fa-2x"></i>
                        <p>Adjuntar archivos (JPG, PNG, PDF)</p>
                        <div id="fileList"></div>
                    </div>
                </div>
            </div>
            
            <div class="users-modal__footer">
                <button type="button" class="btn-modal-secondary" data-ui-accion="cerrar-consulta">Cancelar</button>
                <button type="submit" class="btn-modal-primary">Guardar Consulta</button>
            </div>
        </form>
    </div>
</div>
