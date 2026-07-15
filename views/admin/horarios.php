<?php
$nombre = explode(" ", trim($_SESSION["usuario_nombre"]))[0];
?>

<div class="animate__animated animate__fadeIn">
    <div class="page-header">
        <div>
            <h1>Configuración de Horarios</h1>
            <p>Define los días y horarios de atención de la clínica</p>
        </div>
        <div class="header-actions">
            <button class="btn-multi-day" onclick="abrirModalMultiDia()">
                <i class="fas fa-calendar-week"></i> Configurar varios días
            </button>
            <button class="btn-secondary" onclick="restaurarPorDefecto()">
                Restaurar por Defecto
            </button>
        </div>
    </div>

    <!-- Modal configurar varios días -->
    <div id="modalMultiDia" class="md-overlay" style="display:none;" onclick="cerrarModalMultiDia(event)">
        <div class="md-card" onclick="event.stopPropagation()">
            <div class="md-header">
                <h3>Configurar varios días</h3>
                <button class="md-close" onclick="cerrarModalMultiDia()">&times;</button>
            </div>
            <div class="md-body">
                <!-- Step 1: select days -->
                <div id="mdStep1">
                    <p class="md-hint">Selecciona los días a los que quieres aplicar el mismo horario:</p>
                    <div class="md-days-grid">
                        <?php foreach ([1=>'Lun',2=>'Mar',3=>'Mié',4=>'Jue',5=>'Vie',6=>'Sáb',7=>'Dom'] as $n => $d): ?>
                        <label class="md-day-chip">
                            <input type="checkbox" class="md-day-check" value="<?= $n ?>">
                            <span><?= $d ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- Morning block -->
                    <div class="bloque-section" id="mdMorningSection">
                        <div class="bloque-header">
                            <label>Mañana</label>
                            <label class="switch-sm">
                                <input type="checkbox" id="mdMorningActivo" checked onchange="toggleMdBloque('morning')">
                                <span class="slider round"></span>
                            </label>
                        </div>
                        <div class="bloque-inputs">
                            <input type="time" id="mdMorningInicio" value="08:00">
                            <span>-</span>
                            <input type="time" id="mdMorningFin" value="12:00">
                        </div>
                    </div>

                    <!-- Afternoon block -->
                    <div class="bloque-section" id="mdAfternoonSection">
                        <div class="bloque-header">
                            <label>Tarde</label>
                            <label class="switch-sm">
                                <input type="checkbox" id="mdAfternoonActivo" checked onchange="toggleMdBloque('afternoon')">
                                <span class="slider round"></span>
                            </label>
                        </div>
                        <div class="bloque-inputs">
                            <input type="time" id="mdAfternoonInicio" value="14:00">
                            <span>-</span>
                            <input type="time" id="mdAfternoonFin" value="18:00">
                        </div>
                    </div>
                    <div class="md-error" id="mdError"></div>
                </div>
            </div>
            <div class="md-footer">
                <button class="btn-secondary" onclick="cerrarModalMultiDia()">Cancelar</button>
                <button class="btn-primary" onclick="aplicarMultiDia()">Aplicar a días seleccionados</button>
            </div>
        </div>
    </div>

    <!-- Indicador de guardado -->
    <div class="save-indicator" id="saveIndicator">
        <i class="fas fa-check-circle"></i> Guardado
    </div>

    <!-- Tabla de horarios -->
    <div class="horarios-table-container">
        <table class="horarios-table">
            <thead>
                <tr>
                    <?php
                    $dias_semana = [
                        1 => 'Lunes',
                        2 => 'Martes',
                        3 => 'Miércoles',
                        4 => 'Jueves',
                        5 => 'Viernes',
                        6 => 'Sábado',
                        7 => 'Domingo'
                    ];
                    
                    foreach ($dias_semana as $dia_num => $dia_nombre) {
                        ?>
                        <th data-dia="<?= $dia_num ?>">
                            <div class="th-header">
                                <span><?= $dia_nombre ?></span>
                                <label class="switch-sm">
                                    <input type="checkbox" class="dia-activo" data-dia="<?= $dia_num ?>" checked onchange="toggleDiaActivo(<?= $dia_num ?>)">
                                    <span class="slider round"></span>
                                </label>
                            </div>
                        </th>
                        <?php
                    }
                    ?>
                </tr>
            </thead>
            <tbody id="horariosTableBody">
                <tr>
                    <?php
                    foreach ($dias_semana as $dia_num => $dia_nombre) {
                        ?>
                        <td data-dia="<?= $dia_num ?>">
                            <div class="dia-column">
                                <div class="bloque-section" id="bloque-morning-<?= $dia_num ?>">
                                    <div class="bloque-header">
                                        <label>Mañana</label>
                                        <label class="switch-sm">
                                            <input type="checkbox" class="bloque-activo morning-bloque-activo" data-dia="<?= $dia_num ?>" checked onchange="toggleBloque(<?= $dia_num ?>, 'morning')">
                                            <span class="slider round"></span>
                                        </label>
                                    </div>
                                    <div class="bloque-inputs">
                                        <input type="time" class="morning-inicio time-picker-input" data-dia="<?= $dia_num ?>" value="08:00">
                                        <span>-</span>
                                        <input type="time" class="morning-fin time-picker-input" data-dia="<?= $dia_num ?>" value="12:00">
                                    </div>
                                    <div class="validation-error" id="error-morning-<?= $dia_num ?>"></div>
                                </div>

                                <div class="bloque-section" id="bloque-afternoon-<?= $dia_num ?>">
                                    <div class="bloque-header">
                                        <label>Tarde</label>
                                        <label class="switch-sm">
                                            <input type="checkbox" class="bloque-activo afternoon-bloque-activo" data-dia="<?= $dia_num ?>" checked onchange="toggleBloque(<?= $dia_num ?>, 'afternoon')">
                                            <span class="slider round"></span>
                                        </label>
                                    </div>
                                    <div class="bloque-inputs">
                                        <input type="time" class="afternoon-inicio time-picker-input" data-dia="<?= $dia_num ?>" value="14:00">
                                        <span>-</span>
                                        <input type="time" class="afternoon-fin time-picker-input" data-dia="<?= $dia_num ?>" value="18:00">
                                    </div>
                                    <div class="validation-error" id="error-afternoon-<?= $dia_num ?>"></div>
                                </div>
                            </div>
                        </td>
                        <?php
                    }
                    ?>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<style>
.horarios-table-container {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow-soft);
    border: 1px solid rgba(0, 0, 0, 0.05);
    margin-top: 1.5rem;
    overflow-x: auto;
}

.horarios-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
}

.horarios-table th {
    text-align: center;
    padding: 0.6rem 0.5rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--border-color);
    width: 14.28%;
}

.th-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
    padding: 0.1rem 0;
}

.th-header span {
    line-height: 1;
    transition: opacity 0.3s;
}

.horarios-table td {
    padding: 1rem 0.5rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: top;
    width: 14.28%;
}

.horarios-table tr:last-child td {
    border-bottom: none;
}

.dia-column {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    align-items: center;
}

.bloque-section {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    width: 100%;
}

.bloque-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 0.15rem;
    margin-bottom: 0.2rem;
}

.bloque-header > span,
.bloque-header > label:first-child {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    transition: opacity 0.3s;
}

/* Small self-contained switch */
.switch-sm {
    position: relative;
    display: inline-block;
    width: 28px;
    height: 15px;
    flex-shrink: 0;
    margin: 0;
}

.switch-sm input {
    opacity: 0;
    width: 0;
    height: 0;
    position: absolute;
}

.switch-sm .slider {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background-color: #cbd5e1;
    border-radius: 15px;
    transition: .3s;
}

.switch-sm .slider::before {
    content: "";
    position: absolute;
    height: 11px;
    width: 11px;
    left: 2px;
    bottom: 2px;
    background-color: white;
    border-radius: 50%;
    transition: .3s;
    box-shadow: 0 1px 2px rgba(0,0,0,0.15);
}

.switch-sm input:checked + .slider {
    background-color: var(--primary, #0052FF);
}

.switch-sm input:checked + .slider::before {
    transform: translateX(13px);
}

/* Inactive block */
.bloque-section.bloque-inactivo .bloque-inputs {
    opacity: 0.25;
    pointer-events: none;
    transition: opacity 0.3s;
}

.bloque-section.bloque-inactivo .bloque-header > label:first-child,
.bloque-section.bloque-inactivo .bloque-header > span {
    opacity: 0.4;
}

.bloque-inputs {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    justify-content: center;
}

.bloque-inputs input {
    padding: 0.3rem 0.4rem;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 0.75rem;
    color: var(--text-primary);
    outline: none;
    transition: all 0.2s ease;
    width: 70px;
}

.bloque-inputs input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(85, 96, 255, 0.1);
}

.bloque-inputs span {
    color: var(--text-muted);
    font-size: 0.75rem;
}

/* Switch Toggle */
.switch {
    position: relative;
    display: inline-block;
    width: 40px;
    height: 20px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #cbd5e1;
    transition: .4s;
    border-radius: 20px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 14px;
    width: 14px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: var(--primary);
}

input:checked + .slider:before {
    transform: translateX(20px);
}

.horarios-table td.inactivo {
    background: #f8faff;
}

.horarios-table td.inactivo .dia-column {
    opacity: 0.4;
    pointer-events: none;
}

.horarios-table th.inactivo span {
    opacity: 0.45;
}

/* Multi-day button */
.btn-multi-day {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 1.1rem;
    background: var(--primary, #0052FF);
    color: white;
    border: none;
    border-radius: 8px;
    font-family: inherit;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}
.btn-multi-day:hover {
    background: var(--primary-hover, #003ecc);
}

/* Modal multi-día overlay */
.md-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
    z-index: 9000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.md-card {
    background: white;
    border-radius: 16px;
    width: 100%;
    max-width: 440px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.md-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.1rem 1.4rem 0.9rem;
    border-bottom: 1px solid var(--border-color);
}
.md-header h3 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}
.md-close {
    background: none;
    border: none;
    font-size: 1.4rem;
    line-height: 1;
    color: var(--text-muted);
    cursor: pointer;
    padding: 0 0.2rem;
}
.md-close:hover { color: var(--text-primary); }
.md-body {
    padding: 1.2rem 1.4rem;
    overflow-y: auto;
    max-height: 70vh;
}
.md-hint {
    font-size: 0.82rem;
    color: var(--text-secondary);
    margin: 0 0 0.9rem;
}
.md-days-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1.2rem;
}
.md-day-chip {
    cursor: pointer;
    user-select: none;
}
.md-day-chip input { display: none; }
.md-day-chip span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 38px;
    border-radius: 8px;
    border: 1.5px solid var(--border-color);
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--text-secondary);
    background: #f8faff;
    transition: all 0.18s;
}
.md-day-chip input:checked + span {
    background: var(--primary, #0052FF);
    border-color: var(--primary, #0052FF);
    color: white;
}
.md-body .bloque-section {
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 0.65rem 0.85rem;
    margin-bottom: 0.75rem;
}
.md-error {
    font-size: 0.78rem;
    color: #e53e3e;
    min-height: 1.1em;
    margin-top: 0.3rem;
}
.md-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 0.9rem 1.4rem;
    border-top: 1px solid var(--border-color);
}

.form-actions {
    display: flex;
    justify-content: center;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-color);
    margin-top: 1.5rem;
    gap: 1rem;
}

.btn-primary {
    padding: 0.75rem 2rem;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 8px;
    font-family: inherit;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background: var(--primary-hover);
}

.btn-secondary {
    padding: 0.75rem 2rem;
    background: white;
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-family: inherit;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-secondary:hover {
    background: var(--bg-surface);
    border-color: var(--text-muted);
}

/* Save Indicator */
.save-indicator {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: #10b981;
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.3s ease;
    pointer-events: none;
    z-index: 1000;
}

.save-indicator.show {
    opacity: 1;
    transform: translateY(0);
}

/* Validation Errors */
.validation-error {
    font-size: 0.7rem;
    color: #ef4444;
    margin-top: 0.25rem;
    min-height: 14px;
    text-align: center;
}

.bloque-inputs.has-error .time-picker-display,
.bloque-inputs.has-error .block-picker-display {
    border-color: #ef4444;
    background-color: #fef2f2;
}

/* ── Block Picker Display Button ── */
.block-picker-display {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: 100%;
    padding: 0.45rem 0.6rem;
    background: rgba(0, 82, 255, 0.06);
    border: 1.5px solid rgba(0, 82, 255, 0.35);
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--primary, #0052FF);
    font-family: inherit;
    transition: all 0.2s;
    white-space: nowrap;
}
.block-picker-display:hover,
.block-picker-display:focus {
    background: var(--primary, #0052FF);
    color: white;
    border-color: var(--primary, #0052FF);
    outline: none;
    box-shadow: 0 2px 8px rgba(0,82,255,0.25);
}
.bpd-arrow {
    opacity: 0.6;
    font-size: 0.85em;
}

/* ── Block Picker Modal extras ── */
.bp-card {
    width: 340px !important;
    max-width: 95vw !important;
}
.bp-body {
    display: flex;
    flex-direction: column;
    gap: 0;
}
.bp-row {
    display: flex;
    align-items: center;
    gap: 10px;
}
.bp-label {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--primary, #0052FF);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    width: 36px;
    flex-shrink: 0;
}
.bp-fields {
    display: flex;
    align-items: center;
    gap: 6px;
    flex: 1;
}
.bp-divider {
    height: 1px;
    background: rgba(0, 82, 255, 0.12);
    margin: 10px 0;
}
.bp-error {
    font-size: 0.72rem;
    color: #ef4444;
    font-weight: 600;
    text-align: center;
    padding: 4px 12px 0;
}

/* ── Time Picker Display (chip inside table) ── */
.time-picker-display {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.3rem 0.5rem;
    border: 1.5px solid var(--border-color);
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 500;
    color: var(--text-primary);
    background: #f8faff;
    cursor: pointer;
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    width: 76px;
    white-space: nowrap;
    user-select: none;
}
.time-picker-display:hover,
.time-picker-display:focus {
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 2px rgba(85, 96, 255, 0.12);
    outline: none;
}

/* ── Overlay / Backdrop ── */
.tp-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
.tp-overlay.tp-open {
    display: flex;
}

/* ── Card ── */
.tp-card {
    background: #f0f4ff;
    border-radius: 20px;
    width: 300px;
    max-width: 92vw;
    padding: 1.5rem 1.5rem 1rem;
    box-shadow: 0 24px 64px rgba(0, 0, 0, 0.22);
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    animation: tp-pop 0.18s cubic-bezier(.34,1.56,.64,1) both;
}
@keyframes tp-pop {
    from { opacity: 0; transform: scale(0.88); }
    to   { opacity: 1; transform: scale(1); }
}

/* ── Title ── */
.tp-title {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    color: #6b7280;
    text-transform: uppercase;
}

/* ── Fields row ── */
.tp-fields-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.tp-field-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
}

.tp-field {
    width: 80px;
    height: 68px;
    border-radius: 10px;
    border: 2px solid transparent;
    background: #dce8ff;
    color: #0a1a3a;
    font-size: 2.2rem;
    font-weight: 700;
    text-align: center;
    outline: none;
    transition: border-color 0.18s, background 0.18s, box-shadow 0.18s;
    font-family: inherit;
    caret-color: #0052FF;
}
.tp-field::placeholder {
    color: #8ab0f0;
}
.tp-field:focus,
.tp-field.tp-field-active {
    border-color: #0052FF;
    background: #e8f0ff;
    box-shadow: 0 0 0 3px rgba(0, 82, 255, 0.15);
}

.tp-field-label {
    font-size: 0.68rem;
    font-weight: 600;
    color: #6b7280;
    letter-spacing: 0.04em;
}

/* ── Colon separator ── */
.tp-colon {
    font-size: 2rem;
    font-weight: 700;
    color: #374151;
    align-self: flex-start;
    padding-top: 0.6rem;
    line-height: 1;
}

/* ── AM / PM buttons ── */
.tp-ampm-wrap {
    display: flex;
    flex-direction: column;
    gap: 0;
    border-radius: 10px;
    overflow: hidden;
    border: 1.5px solid #99b8ff;
    align-self: flex-start;
    margin-top: 0;
}

.tp-ampm-fixed {
    border-color: #0052FF;
}

.tp-ampm-btn {
    background: transparent;
    border: none;
    padding: 0.55rem 0.85rem;
    font-size: 0.82rem;
    font-weight: 600;
    color: #0040cc;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
    letter-spacing: 0.04em;
}
.tp-ampm-btn:first-child {
    border-bottom: 1.5px solid #99b8ff;
}
.tp-ampm-fixed .tp-ampm-btn {
    border-bottom: none;
    cursor: default;
}
.tp-ampm-btn.tp-ampm-active {
    background: #0052FF;
    color: white;
}
.tp-ampm-btn:not(.tp-ampm-active):not(:disabled):hover {
    background: #e8f0ff;
}

/* ── Footer ── */
.tp-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.tp-clock-icon {
    color: #9ca3af;
    display: flex;
    align-items: center;
}

.tp-actions {
    display: flex;
    gap: 0.25rem;
    align-items: center;
}

.tp-btn-cancel,
.tp-btn-ok {
    background: none;
    border: none;
    padding: 0.45rem 0.9rem;
    font-size: 0.88rem;
    font-weight: 700;
    cursor: pointer;
    border-radius: 7px;
    transition: background 0.15s;
    font-family: inherit;
    letter-spacing: 0.04em;
    color: #0052FF;
}
.tp-btn-cancel { color: #6b7280; }
.tp-btn-cancel:hover { background: #e5e7eb; }
.tp-btn-ok:hover { background: rgba(0, 82, 255, 0.1); }
</style>

<script src="js/time-picker.js"></script>
<script>
let timePickers = [];

document.addEventListener('DOMContentLoaded', function() {
    cargarHorarios();
    setupAutoSave();
    initializeTimePickers();
});

function initializeTimePickers() {
    // Destroy existing pickers
    timePickers.forEach(tp => tp.destroy());
    timePickers = [];

    // Morning blocks: one BlockPicker per day
    document.querySelectorAll('.morning-inicio').forEach(startInput => {
        const dia = startInput.dataset.dia;
        const endInput = document.querySelector(`.morning-fin[data-dia="${dia}"]`);
        if (!endInput) return;
        timePickers.push(new BlockPicker(startInput, endInput, {
            minuteInterval: 15,
            period: 'morning',
            onTimeChange: () => guardarHorariosSilencioso()
        }));
    });

    // Afternoon blocks: one BlockPicker per day
    document.querySelectorAll('.afternoon-inicio').forEach(startInput => {
        const dia = startInput.dataset.dia;
        const endInput = document.querySelector(`.afternoon-fin[data-dia="${dia}"]`);
        if (!endInput) return;
        timePickers.push(new BlockPicker(startInput, endInput, {
            minuteInterval: 15,
            period: 'afternoon',
            onTimeChange: () => guardarHorariosSilencioso()
        }));
    });
}

function cargarHorarios() {
    fetch('index.php?action=get_horarios_clinica_ajax')
        .then(response => response.json())
        .then(res => {
            if (!res.success) return;
            
            res.horarios.forEach(horario => {
                const cell = document.querySelector(`td[data-dia="${horario.dia_semana}"]`);
                if (!cell) return;
                
                const activoCheckbox = document.querySelector(`.dia-activo[data-dia="${horario.dia_semana}"]`);
                const morningInicio = cell.querySelector('.morning-inicio');
                const morningFin = cell.querySelector('.morning-fin');
                const afternoonInicio = cell.querySelector('.afternoon-inicio');
                const afternoonFin = cell.querySelector('.afternoon-fin');
                
                if (activoCheckbox) {
                    activoCheckbox.checked = horario.activo == 1;
                    toggleDiaActivo(horario.dia_semana, false);
                }

                // Restore block toggles
                const morningToggle   = cell.querySelector('.morning-bloque-activo');
                const afternoonToggle = cell.querySelector('.afternoon-bloque-activo');
                if (morningToggle) {
                    morningToggle.checked = horario.bloque_morning_activo != 0;
                    toggleBloque(horario.dia_semana, 'morning', false);
                }
                if (afternoonToggle) {
                    afternoonToggle.checked = horario.bloque_afternoon_activo != 0;
                    toggleBloque(horario.dia_semana, 'afternoon', false);
                }

                if (morningInicio) {
                    morningInicio.value = horario.bloque_morning_inicio ? horario.bloque_morning_inicio.substring(0, 5) : '';
                }
                if (morningFin) {
                    morningFin.value = horario.bloque_morning_fin ? horario.bloque_morning_fin.substring(0, 5) : '';
                }
                if (afternoonInicio) {
                    afternoonInicio.value = horario.bloque_afternoon_inicio ? horario.bloque_afternoon_inicio.substring(0, 5) : '';
                }
                if (afternoonFin) {
                    afternoonFin.value = horario.bloque_afternoon_fin ? horario.bloque_afternoon_fin.substring(0, 5) : '';
                }
            });
            
            // Reinitialize time pickers after loading values
            setTimeout(() => {
                initializeTimePickers();
            }, 100);
        })
        .catch(error => {
            console.error('Error cargando horarios:', error);
        });
}

function setupAutoSave() {
    // Agregar event listeners a todos los inputs
    const inputs = document.querySelectorAll('.horarios-table input[type="checkbox"]');
    inputs.forEach(input => {
        input.addEventListener('change', () => {
            guardarHorariosSilencioso();
        });
    });
}

function toggleDiaActivo(diaNum, shouldSave = true) {
    const checkbox = document.querySelector(`.dia-activo[data-dia="${diaNum}"]`);
    const cell  = document.querySelector(`td[data-dia="${diaNum}"]`);
    const th    = document.querySelector(`th[data-dia="${diaNum}"]`);

    if (checkbox.checked) {
        cell.classList.remove('inactivo');
        if (th) th.classList.remove('inactivo');
    } else {
        cell.classList.add('inactivo');
        if (th) th.classList.add('inactivo');
    }
    if (shouldSave) guardarHorariosSilencioso();
}

function toggleBloque(diaNum, period, shouldSave = true) {
    const cell    = document.querySelector(`td[data-dia="${diaNum}"]`);
    const toggle  = cell.querySelector(`.${period}-bloque-activo`);
    const section = document.getElementById(`bloque-${period}-${diaNum}`);

    if (toggle.checked) {
        section.classList.remove('bloque-inactivo');
    } else {
        section.classList.add('bloque-inactivo');
    }

    // Auto-activate/deactivate day based on block states
    const morningToggle   = cell.querySelector('.morning-bloque-activo');
    const afternoonToggle = cell.querySelector('.afternoon-bloque-activo');
    const dayToggle       = document.querySelector(`.dia-activo[data-dia="${diaNum}"]`);

    const morningActive   = morningToggle?.checked ?? false;
    const afternoonActive = afternoonToggle?.checked ?? false;

    if (morningActive || afternoonActive) {
        // At least one block active -> activate day
        if (!dayToggle.checked) {
            dayToggle.checked = true;
            toggleDiaActivo(diaNum, false);
        }
    } else {
        // Both blocks inactive -> deactivate day
        if (dayToggle.checked) {
            dayToggle.checked = false;
            toggleDiaActivo(diaNum, false);
        }
    }

    if (shouldSave) guardarHorariosSilencioso();
}

function validateHorarios() {
    let hasErrors = false;
    
    for (let i = 1; i <= 7; i++) {
        const cell = document.querySelector(`td[data-dia="${i}"]`);
        if (!cell) continue;
        
        const activo          = document.querySelector(`.dia-activo[data-dia="${i}"]`).checked;
        if (!activo) continue;

        const morningActivo   = cell.querySelector('.morning-bloque-activo')?.checked !== false;
        const afternoonActivo = cell.querySelector('.afternoon-bloque-activo')?.checked !== false;

        const morningInicio   = cell.querySelector('.morning-inicio').value;
        const morningFin      = cell.querySelector('.morning-fin').value;
        const afternoonInicio = cell.querySelector('.afternoon-inicio').value;
        const afternoonFin    = cell.querySelector('.afternoon-fin').value;

        const morningError    = cell.querySelector('#error-morning-' + i);
        const afternoonError  = cell.querySelector('#error-afternoon-' + i);
        const morningSection  = document.getElementById('bloque-morning-' + i);
        const afternoonSection= document.getElementById('bloque-afternoon-' + i);
        const morningInputs   = morningSection?.querySelector('.bloque-inputs');
        const afternoonInputs = afternoonSection?.querySelector('.bloque-inputs');

        // Clear errors
        if (morningError) morningError.textContent = '';
        if (afternoonError) afternoonError.textContent = '';
        if (morningInputs) morningInputs.classList.remove('has-error');
        if (afternoonInputs) afternoonInputs.classList.remove('has-error');

        // Validate morning block (06:00 - 11:59)
        if (morningActivo && morningInicio && morningFin) {
            const startHour = parseInt(morningInicio.split(':')[0]);
            const endHour   = parseInt(morningFin.split(':')[0]);
            const endMin    = parseInt(morningFin.split(':')[1]);

            if (startHour < 6 || startHour > 11) {
                if (morningError) morningError.textContent = 'Inicio de mañana: entre 6:00 y 11:45 AM';
                if (morningInputs) morningInputs.classList.add('has-error');
                hasErrors = true;
            }
            if (endHour < 6 || endHour > 12 || (endHour === 12 && endMin > 0)) {
                if (morningError) morningError.textContent = 'Fin de mañana: entre 6:00 AM y 12:00 PM (mediodía)';
                if (morningInputs) morningInputs.classList.add('has-error');
                hasErrors = true;
            }
            if (morningFin <= morningInicio) {
                if (morningError) morningError.textContent = 'Hora fin debe ser mayor a hora inicio';
                if (morningInputs) morningInputs.classList.add('has-error');
                hasErrors = true;
            }
        }

        // Validate afternoon block (12:00 PM - 9:00 PM)
        if (afternoonActivo && afternoonInicio && afternoonFin) {
            const startHour = parseInt(afternoonInicio.split(':')[0]);
            const endHour   = parseInt(afternoonFin.split(':')[0]);

            if (startHour < 12 || startHour >= 21) {
                if (afternoonError) afternoonError.textContent = 'Tarde debe ser entre 12:00 PM y 9:00 PM';
                if (afternoonInputs) afternoonInputs.classList.add('has-error');
                hasErrors = true;
            }
            if (endHour < 12 || endHour >= 21) {
                if (afternoonError) afternoonError.textContent = 'Tarde debe ser entre 12:00 PM y 9:00 PM';
                if (afternoonInputs) afternoonInputs.classList.add('has-error');
                hasErrors = true;
            }
            if (afternoonFin <= afternoonInicio) {
                if (afternoonError) afternoonError.textContent = 'Hora fin debe ser mayor a hora inicio';
                if (afternoonInputs) afternoonInputs.classList.add('has-error');
                hasErrors = true;
            }
        }

        // Check for overlap (only if both blocks are active)
        if (morningActivo && afternoonActivo && morningInicio && morningFin && afternoonInicio && afternoonFin) {
            if (morningFin > afternoonInicio) {
                if (morningError) morningError.textContent = 'Los bloques no deben superponerse';
                if (afternoonError) afternoonError.textContent = 'Los bloques no deben superponerse';
                if (morningInputs) morningInputs.classList.add('has-error');
                if (afternoonInputs) afternoonInputs.classList.add('has-error');
                hasErrors = true;
            }
        }
    }
    
    return !hasErrors;
}

function guardarHorariosSilencioso() {
    if (!validateHorarios()) {
        return;
    }
    
    const formData = new FormData();
    
    for (let i = 1; i <= 7; i++) {
        const cell = document.querySelector(`td[data-dia="${i}"]`);
        if (!cell) continue;
        
        const activo          = document.querySelector(`.dia-activo[data-dia="${i}"]`).checked ? 1 : 0;
        const morningActivo   = cell.querySelector('.morning-bloque-activo')?.checked ? 1 : 0;
        const afternoonActivo = cell.querySelector('.afternoon-bloque-activo')?.checked ? 1 : 0;

        // Always save time values to preserve them when reactivating blocks
        const morningInicio   = cell.querySelector('.morning-inicio').value   || null;
        const morningFin      = cell.querySelector('.morning-fin').value       || null;
        const afternoonInicio = cell.querySelector('.afternoon-inicio').value  || null;
        const afternoonFin    = cell.querySelector('.afternoon-fin').value     || null;

        formData.append(`horarios[${i}][activo]`,           activo);
        formData.append(`horarios[${i}][morning_activo]`,   morningActivo);
        formData.append(`horarios[${i}][afternoon_activo]`, afternoonActivo);
        formData.append(`horarios[${i}][morning_inicio]`,   morningInicio);
        formData.append(`horarios[${i}][morning_fin]`,      morningFin);
        formData.append(`horarios[${i}][afternoon_inicio]`, afternoonInicio);
        formData.append(`horarios[${i}][afternoon_fin]`,    afternoonFin);
    }

    fetch('index.php?action=guardar_horarios_clinica_ajax', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(res => {
        if (res.success) {
            showSaveIndicator();
            console.log('Horarios guardados automáticamente');
        } else {
            console.error('Error guardando horarios:', res.message);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: res.message,
                confirmButtonColor: '#5560FF'
            });
        }
    })
    .catch(error => {
        console.error('Error guardando horarios:', error);
    });
}

function showSaveIndicator() {
    const indicator = document.getElementById('saveIndicator');
    if (indicator) {
        indicator.classList.add('show');
        setTimeout(() => {
            indicator.classList.remove('show');
        }, 2000);
    }
}

function restaurarPorDefecto() {
    Swal.fire({
        title: '¿Restaurar horarios por defecto?',
        text: 'Esto reestablecerá todos los horarios a los valores iniciales',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#5560FF',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, restaurar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('index.php?action=restaurar_horarios_defecto_ajax', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(res => {
                if (res.success) {
                    cargarHorarios();
                    Swal.fire({
                        icon: 'success',
                        title: 'Restaurado',
                        text: 'Los horarios se han restaurado correctamente',
                        confirmButtonColor: '#5560FF'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: res.message || 'Error al restaurar los horarios',
                        confirmButtonColor: '#5560FF'
                    });
                }
            })
            .catch(error => {
                console.error('Error restaurando horarios:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al restaurar los horarios',
                    confirmButtonColor: '#5560FF'
                });
            });
        }
    });
}

/* ===================== MULTI-DIA MODAL ===================== */
let mdPickers = [];

function abrirModalMultiDia() {
    document.querySelectorAll('.md-day-check').forEach(c => c.checked = false);
    document.getElementById('mdMorningActivo').checked   = true;
    document.getElementById('mdAfternoonActivo').checked = true;
    document.getElementById('mdMorningSection').classList.remove('bloque-inactivo');
    document.getElementById('mdAfternoonSection').classList.remove('bloque-inactivo');
    document.getElementById('mdError').textContent = '';
    document.getElementById('modalMultiDia').style.display = 'flex';

    // Destroy previous and re-init BlockPickers
    mdPickers.forEach(p => p.destroy && p.destroy());
    mdPickers = [];
    const mStart = document.getElementById('mdMorningInicio');
    const mEnd   = document.getElementById('mdMorningFin');
    const aStart = document.getElementById('mdAfternoonInicio');
    const aEnd   = document.getElementById('mdAfternoonFin');
    mStart.value = '08:00'; mEnd.value = '12:00';
    aStart.value = '14:00'; aEnd.value = '18:00';
    mdPickers.push(new BlockPicker(mStart, mEnd,   { minuteInterval: 15, period: 'morning' }));
    mdPickers.push(new BlockPicker(aStart, aEnd,   { minuteInterval: 15, period: 'afternoon' }));
}

function cerrarModalMultiDia(e) {
    if (e && e.target !== document.getElementById('modalMultiDia')) return;
    document.getElementById('modalMultiDia').style.display = 'none';
}

function toggleMdBloque(period) {
    const activo  = document.getElementById(period === 'morning' ? 'mdMorningActivo' : 'mdAfternoonActivo').checked;
    const section = document.getElementById(period === 'morning' ? 'mdMorningSection' : 'mdAfternoonSection');
    if (activo) {
        section.classList.remove('bloque-inactivo');
    } else {
        section.classList.add('bloque-inactivo');
    }
}

function aplicarMultiDia() {
    const errorEl = document.getElementById('mdError');
    errorEl.textContent = '';

    // Validate at least one day selected
    const selectedDays = [...document.querySelectorAll('.md-day-check:checked')].map(c => parseInt(c.value));
    if (selectedDays.length === 0) {
        errorEl.textContent = 'Selecciona al menos un día.';
        return;
    }

    const morningActivo   = document.getElementById('mdMorningActivo').checked;
    const afternoonActivo = document.getElementById('mdAfternoonActivo').checked;

    if (!morningActivo && !afternoonActivo) {
        errorEl.textContent = 'Activa al menos un bloque (mañana o tarde).';
        return;
    }

    const mInicio = document.getElementById('mdMorningInicio').value;
    const mFin    = document.getElementById('mdMorningFin').value;
    const aInicio = document.getElementById('mdAfternoonInicio').value;
    const aFin    = document.getElementById('mdAfternoonFin').value;

    // Validate morning
    if (morningActivo) {
        if (!mInicio || !mFin) { errorEl.textContent = 'Completa las horas del bloque mañana.'; return; }
        if (mFin <= mInicio)   { errorEl.textContent = 'Mañana: la hora fin debe ser mayor al inicio.'; return; }
        const sh = parseInt(mInicio.split(':')[0]), eh = parseInt(mFin.split(':')[0]), em = parseInt(mFin.split(':')[1]);
        if (sh < 6 || sh > 11)                      { errorEl.textContent = 'Mañana: inicio entre 6:00 y 11:45 AM.'; return; }
        if (eh < 6 || eh > 12 || (eh === 12 && em > 0)) { errorEl.textContent = 'Mañana: fin hasta 12:00 PM (mediodía).'; return; }
    }

    // Validate afternoon
    if (afternoonActivo) {
        if (!aInicio || !aFin) { errorEl.textContent = 'Completa las horas del bloque tarde.'; return; }
        if (aFin <= aInicio)   { errorEl.textContent = 'Tarde: la hora fin debe ser mayor al inicio.'; return; }
        const sh = parseInt(aInicio.split(':')[0]), eh = parseInt(aFin.split(':')[0]);
        if (sh < 12 || sh >= 21) { errorEl.textContent = 'Tarde: inicio entre 12:00 PM y 8:59 PM.'; return; }
        if (eh < 12 || eh >= 21) { errorEl.textContent = 'Tarde: fin entre 12:00 PM y 9:00 PM.'; return; }
    }

    // Apply to each selected day
    selectedDays.forEach(diaNum => {
        const cell = document.querySelector(`td[data-dia="${diaNum}"]`);
        if (!cell) return;

        const dayToggle       = document.querySelector(`.dia-activo[data-dia="${diaNum}"]`);
        const morningToggle   = cell.querySelector('.morning-bloque-activo');
        const afternoonToggle = cell.querySelector('.afternoon-bloque-activo');
        const mInicioInput    = cell.querySelector('.morning-inicio');
        const mFinInput       = cell.querySelector('.morning-fin');
        const aInicioInput    = cell.querySelector('.afternoon-inicio');
        const aFinInput       = cell.querySelector('.afternoon-fin');

        // Set block toggles and times
        morningToggle.checked   = morningActivo;
        afternoonToggle.checked = afternoonActivo;

        if (morningActivo)   { mInicioInput.value = mInicio; mFinInput.value = mFin; }
        if (afternoonActivo) { aInicioInput.value = aInicio; aFinInput.value = aFin; }

        // Update block UI
        toggleBloque(diaNum, 'morning',   false);
        toggleBloque(diaNum, 'afternoon', false);

        // Activate the day
        dayToggle.checked = true;
        toggleDiaActivo(diaNum, false);
    });

    cerrarModalMultiDia();
    guardarHorariosSilencioso();
}
</script>
