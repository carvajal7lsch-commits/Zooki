<!-- MODAL NUEVA CONSULTA -->
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

        <form id="formConsulta" onsubmit="saveConsultation(event)" enctype="multipart/form-data">
            <input type="hidden" name="id_mascota" id="consultation_id_mascota">
            
            <div class="users-modal__body">
                <div id="tabMotivo" class="modal-tab-content active">
                    <div class="input-group full">
                        <label>Motivo de Consulta *</label>
                        <input type="text" name="motivo" required>
                    </div>
                    <div class="input-group full">
                        <label>Anamnesis</label>
                        <textarea name="anamnesis" rows="4"></textarea>
                    </div>
                </div>

                <div id="tabSignos" class="modal-tab-content">
                    <div class="vitals-dashboard-grid">
                        <div class="vital-card">
                            <div class="vital-sign-icon weight">
                                <i class="fas fa-weight"></i>
                            </div>
                            <div class="vital-input-col">
                                <label>Peso (Kg)</label>
                                <input type="number" step="0.01" name="peso">
                            </div>
                        </div>
                        <div class="vital-card">
                            <div class="vital-sign-icon temp">
                                <i class="fas fa-thermometer-half"></i>
                            </div>
                            <div class="vital-input-col">
                                <label>Temp (°C)</label>
                                <input type="number" step="0.1" name="temperatura">
                            </div>
                        </div>
                        <div class="vital-card">
                            <div class="vital-sign-icon heart">
                                <i class="fas fa-heartbeat"></i>
                            </div>
                            <div class="vital-input-col">
                                <label>F.C. (LPM)</label>
                                <input type="number" name="frecuencia_cardiaca">
                            </div>
                        </div>
                    </div>
                </div>

                <div id="tabResolucion" class="modal-tab-content">
                    <div class="input-group full">
                        <label>Diagnóstico *</label>
                        <textarea name="diagnostico" rows="3" required></textarea>
                    </div>
                    <div class="input-group full">
                        <label>Plan / Recomendaciones</label>
                        <textarea name="plan_tratamiento" rows="2"></textarea>
                    </div>
                    <div class="treatments-section">
                        <div class="section-title-action">
                            <label>Tratamientos</label>
                            <button type="button" class="btn-add-treatment" data-ui-accion="agregar-tratamiento"><i class="fas fa-plus"></i> Agregar</button>
                        </div>
                        <div id="treatmentsList"></div>
                    </div>
                </div>
                
                <div id="tabArchivos" class="modal-tab-content">
                    <div class="file-upload-zone">
                        <input type="file" name="archivos[]" multiple accept=".jpg,.jpeg,.png,.pdf" onchange="updateFileList(this)">
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
