<?php
// views/mascotas/listado.php
?>
<div class="section-card">
    <!-- Super Header Compacto -->
    <div class="command-center-hero">

        <!-- Fila Superior: Título y Acciones Principales -->
        <div class="command-center-header-row">
            <div class="command-center-title-group">
                <h2 class="command-center-title"><i class="fas fa-shield-alt"></i> Centro de Mando</h2>
                <div class="command-center-tabs">
                    <button onclick="switchModule('owners')" id="tabOwners" class="command-center-tab-btn active">
                        <i class="fas fa-users"></i> Propietarios
                    </button>
                    <button onclick="switchModule('pets')" id="tabPets" class="command-center-tab-btn">
                        <i class="fas fa-paw"></i> Mascotas
                    </button>
                </div>
            </div>

            <div class="command-center-actions">
                <?php if ($_SESSION["usuario_id_rol"] != 2): ?>
                <button onclick="openModal('modalPropietario')" class="command-center-btn-secondary">
                    <i class="fas fa-user-plus"></i> Propietario
                </button>
                <button onclick="openModal('modalMascota')" class="command-center-btn-primary">
                    <i class="fas fa-plus"></i> Nueva Mascota
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Fila Inferior: Buscador, Filtros y Stats -->
        <div class="command-center-search-row">
            <div class="search-box-inline" style="flex: 1; max-width: 600px;">
                <i class="fas fa-search"></i>
                <input type="text" id="tableSearch" placeholder="Buscar por nombre, dueño o HC..." onkeyup="filterTable()"
                       style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.75rem 1rem 0.75rem 2.75rem; border-radius: 12px; font-size: 0.9rem;">
            </div>

            <div style="display: flex; align-items: center; gap: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem; background: #f8fafc; padding: 0.5rem 1rem; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <span style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted);">FILTRAR:</span>
                    <select onchange="filterTableBySpecies(this.value)" style="border: none; background: transparent; font-weight: 700; color: var(--primary); outline: none; cursor: pointer; font-size: 0.85rem;">
                        <option value="">Todas las especies</option>
                        <option value="Canino">Caninos</option>
                        <option value="Felino">Felinos</option>
                    </select>
                </div>

                <div style="display: flex; align-items: center; gap: 1rem; border-left: 1px solid #e2e8f0; padding-left: 1.5rem;">
                    <div class="view-switcher">
                        <button onclick="switchView('list')" id="btnViewList" class="view-btn" title="Vista Lista"><i class="fas fa-list"></i></button>
                        <button onclick="switchView('grid')" id="btnViewGrid" class="view-btn" title="Vista Cuadrícula"><i class="fas fa-th-large"></i></button>
                    </div>
                    <div style="text-align: right;">
                        <span style="display: block; font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Total Pacientes</span>
                        <span style="font-size: 1.1rem; font-weight: 800; color: var(--primary);"><?php echo count(
                            $mascotas,
                        ); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CONTENEDOR PRINCIPAL DEL MÓDULO -->
    <div id="moduleOwners" class="module-section active">

        <!-- VISTA 1: Directorio de Clientes -->
        <div id="ownersDirectory" class="view-container active">
            <div class="pets-grid" id="ownersGrid">
                <!-- Se carga vía AJAX -->
            </div>
        </div>

        <!-- VISTA 2: Expediente Detallado (Dossier) - Se muestra al elegir un cliente -->
        <div id="dossierView" class="view-container dossier-view-active" style="display: none; animation: fadeIn 0.4s ease;">

            <!-- CONTENEDOR 1: LISTADO DE PACIENTES (Layout Compacto Horizontal) -->
            <div id="dossierListContainer" class="dossier-compact-container">

                <!-- Columna Izquierda: Datos Propietario (Compacto) -->
                <div class="dossier-owner-compact">
                    <!-- Cabecera de la ficha -->
                    <div class="dossier-owner-header">
                        <h4><i class="fas fa-user-circle"></i> Propietario</h4>
                        <div class="dossier-owner-actions">
                            <button onclick="editOwnerFromDossier()" class="command-center-btn-secondary" style="padding: 0.3rem 0.6rem; font-size: 0.75rem;">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            <button onclick="hideDossier()" class="command-center-btn-secondary" style="padding: 0.3rem 0.6rem; font-size: 0.75rem;">
                                <i class="fas fa-chevron-left"></i> Volver
                            </button>
                        </div>
                    </div>

                    <!-- Datos del Propietario -->
                    <div class="dossier-owner-info">
                        <div class="dossier-owner-field">
                            <label>Nombre</label>
                            <span id="dossierOwnerName">---</span>
                        </div>
                        <div class="dossier-owner-field">
                            <label>Doc</label>
                            <span id="dossierOwnerDoc">---</span>
                        </div>
                        <div class="dossier-owner-field">
                            <label>Tel</label>
                            <span id="dossierOwnerPhone">---</span>
                        </div>
                        <div class="dossier-owner-field">
                            <label>Email</label>
                            <span id="dossierOwnerEmail">---</span>
                        </div>
                        <div class="dossier-owner-field">
                            <label>Estado</label>
                            <span id="dossierOwnerEstado"></span>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Mascotas con Scroll Horizontal -->
                <div class="dossier-pets-horizontal">
                    <div class="dossier-pets-header">
                        <h4><i class="fas fa-paw"></i> Pacientes</h4>
                        <button onclick="addNewPetFromDossier()" class="command-center-btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.75rem;">
                            <i class="fas fa-plus"></i> Nuevo
                        </button>
                    </div>

                    <div class="dossier-pets-scroll" id="dossierPetsScroll">
                        <!-- Cards de mascotas se cargarán vía JS -->
                    </div>
                </div>
            </div>

            <!-- CONTENEDOR 2: EXPEDIENTE DETALLADO MASCOTA (Dashboard Compacto) -->
            <div id="dossierDashboardContainer" class="dossier-dashboard-container" style="display: none;">

                <!-- Header Compacto: Título + Propietario -->
                <div style="display: flex; gap: 1rem; align-items: stretch;">
                    <!-- Tarjeta de Navegación -->
                    <div class="dossier-dashboard-header" style="flex: 1;">
                        <div class="dossier-dashboard-title">
                            <button onclick="showPetsListFromDossier()" class="command-center-btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">
                                <i class="fas fa-arrow-left"></i> Volver
                            </button>
                            <h4><i class="fas fa-paw"></i> <span id="dashPetName"></span></h4>
                        </div>
                        <div class="dossier-dashboard-actions">
                            <button id="dashEditPetBtn" class="command-center-btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                        </div>
                    </div>

                    <!-- Tarjeta Compacta del Propietario -->
                    <div class="dossier-dashboard-owner">
                        <div class="dossier-dashboard-owner-info">
                            <h5><i class="fas fa-user-circle"></i> Propietario</h5>
                            <div class="dossier-dashboard-owner-details">
                                <span><strong id="dossierOwnerNameDash">---</strong></span>
                                <span><strong id="dossierOwnerPhoneDash">---</strong></span>
                                <span id="dossierOwnerEstadoDash"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs del Dashboard -->
                <div class="dossier-dashboard-tabs">
                    <button class="dossier-dashboard-tab active" onclick="switchPetDashTab(event, 'dashGeneral')">
                        <i class="fas fa-info-circle"></i> Información General
                    </button>
                    <button class="dossier-dashboard-tab" onclick="switchPetDashTab(event, 'dashHistorial')">
                        <i class="fas fa-notes-medical"></i> Historia Clínica
                    </button>
                </div>

                <!-- Contenido del Dashboard -->
                <div class="dossier-dashboard-content">

                            <!-- PESTAÑA: INFORMACIÓN GENERAL -->
                            <div id="dashGeneral" class="dash-tab-content active" style="display: block;">
                                <div class="dossier-pet-info-grid">
                                    <!-- Foto del paciente -->
                                    <div style="position: relative;">
                                        <img id="dashPetPhoto" src="img/default-pet.png" class="dossier-pet-photo">
                                        <div id="dashPetStatus" style="position: absolute; bottom: 8px; right: 8px; width: 14px; height: 14px; border-radius: 50%; border: 3px solid white;"></div>
                                    </div>

                                    <!-- Campos de datos detallados -->
                                    <div class="dossier-pet-details">
                                        <div class="dossier-pet-field">
                                            <label>Especie</label>
                                            <span id="dashPetEspecie">---</span>
                                        </div>
                                        <div class="dossier-pet-field">
                                            <label>Raza</label>
                                            <span id="dashPetRaza">---</span>
                                        </div>
                                        <div class="dossier-pet-field">
                                            <label>Sexo</label>
                                            <span id="dashPetSexo">---</span>
                                        </div>
                                        <div class="dossier-pet-field">
                                            <label>Peso actual</label>
                                            <span id="dashPetPeso">---</span>
                                        </div>
                                        <div class="dossier-pet-field">
                                            <label>Historia Clínica</label>
                                            <code id="dashPetHC" class="hc-badge">---</code>
                                        </div>
                                        <div class="dossier-pet-field">
                                            <label>Fecha de Nacimiento</label>
                                            <span id="dashPetFechaNac">---</span>
                                        </div>
                                        <div class="dossier-pet-field" style="grid-column: span 2; border-top: 1px solid #f1f5f9; padding-top: 1rem; margin-top: 0.5rem;">
                                            <label>Colores Base</label>
                                            <div id="dashPetColores" style="display: flex; gap: 0.4rem; flex-wrap: wrap;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- PESTAÑA: CONSULTAS MÉDICAS -->
                            <div id="dashConsultas" class="dash-tab-content" style="display: none;">
                                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem; margin-bottom: 1rem;">
                                    <h5 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 0.4rem;">
                                        <i class="fas fa-stethoscope" style="color: var(--primary);"></i> Consultas Clínicas
                                    </h5>
                                    <?php if (
                                        $_SESSION["usuario_id_rol"] == 1 ||
                                        $_SESSION["usuario_id_rol"] == 2
                                    ): ?>
                                    <button id="dashNewConsultaBtn" class="btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; border-radius: 8px;">
                                        <i class="fas fa-plus"></i> Nueva Consulta
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <div class="table-container" style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: white; margin: 0;">
                                    <table class="modern-table" style="margin: 0; width: 100%; font-size: 0.85rem;">
                                        <thead>
                                            <tr>
                                                <th style="padding: 0.75rem; background: #f8fafc; color: var(--text-muted); font-weight: 800;">Fecha</th>
                                                <th style="padding: 0.75rem; background: #f8fafc; color: var(--text-muted); font-weight: 800;">Motivo</th>
                                                <th style="padding: 0.75rem; background: #f8fafc; color: var(--text-muted); font-weight: 800;">Diagnóstico</th>
                                                <th style="padding: 0.75rem; background: #f8fafc; color: var(--text-muted); font-weight: 800; text-align: right;">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="dashConsultasBody">
                                            <!-- Carga vía JS -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- PESTAÑA: VACUNAS -->
                            <div id="dashVacunas" class="dash-tab-content" style="display: none;">
                                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem; margin-bottom: 1rem;">
                                    <h5 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 0.4rem;">
                                        <i class="fas fa-syringe" style="color: var(--primary);"></i> Vacunas Aplicadas
                                    </h5>
                                    <?php if (
                                        $_SESSION["usuario_id_rol"] == 1 ||
                                        $_SESSION["usuario_id_rol"] == 2
                                    ): ?>
                                    <button id="dashNewVacunaBtn" class="btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; border-radius: 8px;">
                                        <i class="fas fa-plus"></i> Aplicar Vacuna
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <div class="table-container" style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: white; margin: 0;">
                                    <table class="modern-table" style="margin: 0; width: 100%; font-size: 0.85rem;">
                                        <thead>
                                            <tr>
                                                <th style="padding: 0.75rem; background: #f8fafc; color: var(--text-muted); font-weight: 800;">Fecha</th>
                                                <th style="padding: 0.75rem; background: #f8fafc; color: var(--text-muted); font-weight: 800;">Nombre Vacuna</th>
                                                <th style="padding: 0.75rem; background: #f8fafc; color: var(--text-muted); font-weight: 800;">Lote</th>
                                                <th style="padding: 0.75rem; background: #f8fafc; color: var(--text-muted); font-weight: 800;">Próxima Dosis</th>
                                            </tr>
                                        </thead>
                                        <tbody id="dashVacunasBody">
                                            <!-- Carga vía JS -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- PESTAÑA: DESPARASITACIONES -->
                            <div id="dashDesparasitaciones" class="dash-tab-content" style="display: none;">
                                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem; margin-bottom: 1rem;">
                                    <h5 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 0.4rem;">
                                        <i class="fas fa-bug" style="color: var(--primary);"></i> Control de Parásitos
                                    </h5>
                                    <?php if (
                                        $_SESSION["usuario_id_rol"] == 1 ||
                                        $_SESSION["usuario_id_rol"] == 2
                                    ): ?>
                                    <button id="dashNewDesparasitacionBtn" class="btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; border-radius: 8px;">
                                        <i class="fas fa-plus"></i> Desparasitar
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <div class="table-container" style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: white; margin: 0;">
                                    <table class="modern-table" style="margin: 0; width: 100%; font-size: 0.85rem;">
                                        <thead>
                                            <tr>
                                                <th style="padding: 0.75rem; background: #f8fafc; color: var(--text-muted); font-weight: 800;">Fecha</th>
                                                <th style="padding: 0.75rem; background: #f8fafc; color: var(--text-muted); font-weight: 800;">Producto</th>
                                                <th style="padding: 0.75rem; background: #f8fafc; color: var(--text-muted); font-weight: 800;">Tipo</th>
                                                <th style="padding: 0.75rem; background: #f8fafc; color: var(--text-muted); font-weight: 800;">Próxima Dosis</th>
                                            </tr>
                                        </thead>
                                        <tbody id="dashDesparasitacionesBody">
                                            <!-- Carga vía JS -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- PESTAÑA: HISTORIA CLÍNICA UNIFICADA -->
                            <div id="dashHistorial" class="dash-tab-content" style="display: none;">
                                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem; margin-bottom: 1rem;">
                                    <h5 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 0.4rem;">
                                        <i class="fas fa-notes-medical" style="color: var(--primary);"></i> Línea de Tiempo Clínica
                                    </h5>
                                    <button onclick="viewMedicalHistory(document.getElementById('dashEditPetBtn').getAttribute('data-id'), document.getElementById('dashPetName').innerText)" class="btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; border-radius: 8px; cursor: pointer;">
                                        <i class="fas fa-print"></i> Historial Completo
                                    </button>
                                </div>
                                <div id="dashHistorialTimeline" style="display: flex; flex-direction: column; gap: 1rem; max-height: 400px; overflow-y: auto; padding-right: 0.5rem;">
                                    <!-- Carga vía JS -->
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECCIÓN DE MASCOTAS (Prioridad 2) -->
    <div id="modulePets" class="module-section">

        <div id="listView" class="view-container active">
            <div class="table-container">
                <table class="modern-table" id="petsTable">
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Paciente</th>
                            <th>Especie / Raza</th>
                            <th>Colores</th>
                            <th>Propietario</th>
                            <th>HC</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($mascotas) > 0): ?>
                            <?php foreach ($mascotas as $m): ?>
                                <tr data-id="<?php echo $m["id_mascota"]; ?>">
                                    <td>
                                        <img src="<?php echo $m["url_foto"]
                                            ? "uploads/mascotas/" .
                                                $m["url_foto"]
                                            : "img/default-pet.png"; ?>"
                                             class="table-thumb"
                                             onclick="viewImage(this.src)"
                                             onerror="this.src='https://ui-avatars.com/api/?name=Pet&background=random'">
                                    </td>
                                    <td class="pet-name-cell">
                                        <div class="cell-info">
                                            <span class="main-text"><?php echo $m[
                                                "nombre"
                                            ]; ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="cell-info">
                                            <span class="main-text"><?php echo $m[
                                                "nombre_especie"
                                            ]; ?> - <?php echo $m[
     "nombre_raza"
 ] ?? "N/A"; ?></span>
                                            <span class="sub-text"><?php echo $m[
                                                "sexo"
                                            ]; ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="cell-info">
                                            <span class="main-text"><?php echo $m[
                                                "colores_nombres"
                                            ] ?? "N/A"; ?></span>
                                        </div>
                                    </td>
                                    <td class="owner-name-cell">
                                        <span class="main-text"><?php echo $m[
                                            "propietario_nombre"
                                        ]; ?></span>
                                        <small style="display:block; color:#666;"><?php echo $m[
                                            "doc_propietario"
                                        ]; ?></small>
                                    </td>
                                    <td>
                                        <code class="hc-badge"><?php echo $m[
                                            "numero_historia_clinica"
                                        ]; ?></code>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $m[
                                            "estado"
                                        ] == 1
                                            ? "active"
                                            : "inactive"; ?>">
                                            <?php echo $m["estado"] == 1
                                                ? "Activo"
                                                : "Inactivo"; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button onclick="openAppointmentModal(<?php echo $m[
                                                "id_mascota"
                                            ]; ?>, '<?php echo $m[
    "nombre"
]; ?>')" class="btn-icon medical" title="Agendar Cita" style="background:#eff6ff; color:#3b82f6; border: 1px solid #dbeafe;">
                                                <i class="far fa-calendar-check"></i>
                                            </button>
                                            <?php if (
                                                $_SESSION["usuario_id_rol"] ==
                                                    1 ||
                                                $_SESSION["usuario_id_rol"] == 2
                                            ): ?>
                                            <button onclick="openConsultationModal(<?php echo $m[
                                                "id_mascota"
                                            ]; ?>, '<?php echo $m[
    "nombre"
]; ?>')" class="btn-icon medical" title="Nueva Consulta">
                                                <i class="fas fa-stethoscope"></i>
                                            </button>
                                            <button onclick="openVaccineModal(<?php echo $m[
                                                "id_mascota"
                                            ]; ?>, '<?php echo $m[
    "nombre"
]; ?>')" class="btn-icon history" title="Registrar Vacuna" style="background:#f0fdf4; color:#16a34a; border: 1px solid #bbf7d0;">
                                                <i class="fas fa-syringe"></i>
                                            </button>
                                            <button onclick="openDewormingModal(<?php echo $m[
                                                "id_mascota"
                                            ]; ?>, '<?php echo $m[
    "nombre"
]; ?>')" class="btn-icon history" title="Registrar Desparasitación" style="background:#fff7ed; color:#ea580c; border: 1px solid #ffedd5;">
                                                <i class="fas fa-bug"></i>
                                            </button>
                                            <?php endif; ?>
                                            <button onclick="viewMedicalHistory(<?php echo $m[
                                                "id_mascota"
                                            ]; ?>, '<?php echo $m[
    "nombre"
]; ?>')" class="btn-icon history" title="Ver Historial">
                                                <i class="fas fa-notes-medical"></i>
                                            </button>

                                            <button onclick="editPet(<?php echo $m[
                                                "id_mascota"
                                            ]; ?>)" class="btn-icon edit" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty-table">
                                    <i class="fas fa-paw"></i>
                                    <p>No hay mascotas registradas aún.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="gridView" class="view-container">
            <div class="pets-grid" id="petsGrid">
                <?php foreach ($mascotas as $m): ?>
                    <div class="pet-card" data-id="<?php echo $m[
                        "id_mascota"
                    ]; ?>" data-species="<?php echo $m["nombre_especie"]; ?>">
                        <div class="card-photo">
                            <img src="<?php echo $m["url_foto"]
                                ? "uploads/mascotas/" . $m["url_foto"]
                                : "img/default-pet.png"; ?>"
                                 onclick="viewImage(this.src)"
                                 onerror="this.src='https://ui-avatars.com/api/?name=Pet&background=random'">
                            <div class="card-status <?php echo $m["estado"] == 1
                                ? "active"
                                : "inactive"; ?>"></div>
                        </div>
                        <div class="card-info">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 0.5rem;">
                                <h3 style="margin:0;"><?php echo $m[
                                    "nombre"
                                ]; ?></h3>
                                <code class="hc-badge"><?php echo $m[
                                    "numero_historia_clinica"
                                ]; ?></code>
                            </div>
                            <p class="species" style="margin-bottom: 0.25rem;">
                                <i class="fas fa-paw"></i> <?php echo $m[
                                    "nombre_especie"
                                ]; ?> • <?php echo htmlspecialchars($m["nombre_raza"] ?? ''); ?><?php if (!empty($m['raza_indicada'])): ?> (indicó «<?php echo htmlspecialchars($m['raza_indicada']); ?>»)<?php endif; ?>
                            </p>
                            <p style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500; margin-bottom: 1rem;">
                                <i class="fas fa-venus-mars"></i> <?php echo $m[
                                    "sexo"
                                ]; ?>
                            </p>

                            <div class="owner-info" style="background: #f8fafc; padding: 0.75rem; border-radius: 12px; margin-bottom: 1.5rem;">
                                <i class="fas fa-user-circle" style="color: var(--primary); font-size: 1.2rem;"></i>
                                <div style="display:flex; flex-direction:column;">
                                    <span style="font-weight: 700; font-size: 0.85rem;"><?php echo $m[
                                        "propietario_nombre"
                                    ]; ?></span>
                                    <span style="font-size: 0.75rem; color: var(--text-muted);">Propietario</span>
                                </div>
                            </div>

                            <div class="card-actions" style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; border:none; padding:0;">
                                <?php if (
                                    $_SESSION["usuario_id_rol"] == 1 ||
                                    $_SESSION["usuario_id_rol"] == 2
                                ): ?>
                                <?php
                                    // Ajustar grid si se ocultan botones
                                    ?>
                                <button onclick="openConsultationModal(<?php echo $m[
                                    "id_mascota"
                                ]; ?>, '<?php echo $m[
    "nombre"
]; ?>')" class="btn-icon medical" title="Nueva Consulta">
                                    <i class="fas fa-stethoscope"></i>
                                </button>
                                <?php endif; ?>
                                <button onclick="openAppointmentModal(<?php echo $m[
                                    "id_mascota"
                                ]; ?>, '<?php echo $m[
    "nombre"
]; ?>')" class="btn-icon medical" title="Agendar Cita">
                                    <i class="far fa-calendar-check"></i>
                                </button>
                                <button onclick="viewMedicalHistory(<?php echo $m[
                                    "id_mascota"
                                ]; ?>, '<?php echo $m[
    "nombre"
]; ?>')" class="btn-icon history" title="Ver Historial">
                                    <i class="fas fa-notes-medical"></i>
                                </button>
                                <?php if (
                                    $_SESSION["usuario_id_rol"] == 1 ||
                                    $_SESSION["usuario_id_rol"] == 2
                                ): ?>
                                <button onclick="openVaccineModal(<?php echo $m[
                                    "id_mascota"
                                ]; ?>, '<?php echo $m[
    "nombre"
]; ?>')" class="btn-icon history" title="Vacuna">
                                    <i class="fas fa-syringe"></i>
                                </button>
                                <button onclick="openDewormingModal(<?php echo $m[
                                    "id_mascota"
                                ]; ?>, '<?php echo $m[
    "nombre"
]; ?>')" class="btn-icon history" title="Desparasitar">
                                    <i class="fas fa-bug"></i>
                                </button>
                                <?php endif; ?>
                                <button onclick="editPet(<?php echo $m[
                                    "id_mascota"
                                ]; ?>)" class="btn-icon edit" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- SECCIÓN DE PROPIETARIOS -->
    <div id="moduleOwners" class="module-section">
        <div class="table-tools">
            <div class="search-box-inline">
                <i class="fas fa-search"></i>
                <input type="text" id="ownerSearch" placeholder="Buscar por nombre, documento o email..." onkeyup="filterOwners()">
            </div>
            <div class="view-switcher">
                <button onclick="switchOwnerView('list')" id="btnOwnerViewList" class="view-btn active" title="Vista Lista">
                    <i class="fas fa-list"></i>
                </button>
                <button onclick="switchOwnerView('grid')" id="btnOwnerViewGrid" class="view-btn" title="Vista Cuadrícula">
                    <i class="fas fa-th-large"></i>
                </button>
            </div>
        </div>

        <div id="ownerListView" class="view-container active">
            <div class="table-container">
                <table class="modern-table" id="ownersTable">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Nombre Completo</th>
                            <th>Contacto</th>
                            <th>Email</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="ownersTableBody">
                        <!-- Se cargará por AJAX -->
                    </tbody>
                </table>
            </div>
        </div>

        <div id="ownerGridView" class="view-container">
            <div class="pets-grid" id="ownersGrid">
                <!-- Se cargará por AJAX -->
            </div>
        </div>
    </div>
</div>


<!-- MODAL EDITAR MASCOTA -->
<div id="modalEditarMascota" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Editar Mascota</h3>
            <span class="close" onclick="closeModal('modalEditarMascota')">&times;</span>
        </div>
        <form id="formEditMascota" onsubmit="updatePet(event)" enctype="multipart/form-data">
            <input type="hidden" name="id_mascota" id="edit_id">
            <div class="modal-body">
                <div class="form-grid-split">
                    <div class="photo-side">
                        <div class="preview-box">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Arrastrar o clic</span>
                            <img id="editPreview" src="">
                        </div>
                        <input type="file" name="foto" id="editFoto" accept="image/*" onchange="previewImg(this, 'editPreview')" style="display:none;">

                        <div style="margin-top: 1rem; display: flex; flex-direction: column; gap: 0.75rem; padding: 0.75rem; background: #f8fafc; border-radius: 16px; border: 1px solid #e2e8f0;">
                            <div class="input-group">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 0.25rem;">
                                    <label style="margin:0;"><i class="fas fa-palette"></i> Colores Base</label>
                                    <button type="button" onclick="promptAddNewColor('editColorChips', 'editSelectedColoresInput')" style="border:none; background:transparent; color:var(--primary); font-weight:800; font-size:0.75rem; cursor:pointer; display:flex; align-items:center; gap:4px; padding:0; outline:none;"><i class="fas fa-plus-circle"></i> Nuevo Color</button>
                                </div>
                                <div id="editColorChips" class="chips-container" style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px;"></div>
                                <div id="editSelectedColoresInput"></div>
                            </div>
                            <div class="input-group">
                                <label><i class="fas fa-toggle-on"></i> Estado de la Mascota</label>
                                <select name="estado" id="edit_estado" required style="border: 1px solid #e2e8f0; width: 100%;">
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="data-side">
                        <div class="form-grid-pet">
                            <div class="input-group span-2">
                                <label>Nombre Mascota</label>
                                <input type="text" name="nombre" id="edit_nombre" required>
                            </div>
                            <div class="input-group">
                                <label>Sexo</label>
                                <select name="sexo" id="edit_sexo" required>
                                    <option value="Macho">Macho</option>
                                    <option value="Hembra">Hembra</option>
                                    <option value="Desconocido">Desconocido</option>
                                </select>
                            </div>
                            <div class="input-group">
                                <label>Especie</label>
                                <select name="especie" id="edit_especie" required onchange="loadBreeds(this.value, 'edit_raza')">
                                    <option value="">Seleccione...</option>
                                </select>
                            </div>
                            <div class="input-group">
                                <label>Raza</label>
                                <select name="raza" id="edit_raza" required onchange="checkOtherBreed(this, 'editOtherBreedGroup')">
                                    <option value="">Seleccione especie...</option>
                                </select>
                            </div>
                            <div class="input-group span-3" id="editOtherBreedGroup" style="display:none;">
                                <label>Especifique la Raza</label>
                                <input type="text" name="nueva_raza" id="edit_nueva_raza" placeholder="¿Qué raza es?">
                            </div>
                            <div class="input-group">
                                <label>Peso (Kg)</label>
                                <input type="number" step="0.01" name="peso" id="edit_peso" required>
                            </div>
                            <div class="input-group">
                                <label>Fecha Nacimiento *</label>
                                <input type="date" name="fecha_nacimiento" id="edit_fecha_nac" required>
                            </div>
                            <div class="input-group" style="position: relative;">
                                <label>Cambiar Propietario (Opcional)</label>
                                <input type="text" id="editOwnerSearchInput" placeholder="Buscar nuevo dueño..." onkeyup="searchOwnersForEdit(this.value)" autocomplete="off">
                                <input type="hidden" name="doc_propietario" id="edit_petOwnerDoc">
                                <div id="editOwnerSuggestions" class="suggestions-list"></div>
                                <div id="selectedEditOwnerInfo" class="selected-badge" style="display:none;">
                                    <i class="fas fa-user-check"></i> <span id="selectedEditOwnerName"></span>
                                    <i class="fas fa-times-circle" onclick="clearEditOwnerSelection()" style="cursor:pointer; margin-left: auto;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn-primary full-btn">Actualizar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL NUEVA CONSULTA -->
<?php include __DIR__ . "/../modal_consulta.php"; ?>

<!-- DRAWER HISTORIAL MÉDICO -->
<div id="drawerHistorialOverlay" class="drawer-overlay" onclick="closeDrawer('drawerHistorial')"></div>
<aside id="drawerHistorial" class="drawer">
    <div class="drawer-header">
        <button type="button" class="drawer-close" onclick="closeDrawer('drawerHistorial')" aria-label="Cerrar">
            <i class="fas fa-times"></i>
        </button>
        <div class="drawer-title-wrap">
            <h2><i class="fas fa-notes-medical"></i> Historial Clínico</h2>
            <p id="historyPetName"></p>
        </div>
    </div>
    <div class="drawer-body">
        <!-- Encabezado de Historia Clínica -->
        <div class="history-pet-header">
            <div class="hc-number-badge">
                <label>N° Historia Clínica</label>
                <span id="historyHCNumber">---</span>
            </div>
            <div class="pet-summary-quick">
                <span id="historyPetSpecie">---</span> • <span id="historyPetAge">---</span>
            </div>
        </div>

        <!-- Resumen de Vacunación -->
        <div class="vaccine-summary-section">
            <h4 class="section-title"><i class="fas fa-syringe"></i> Resumen de Vacunación</h4>
            <div id="vaccineList" class="vaccine-grid">
                <!-- Dinámico -->
            </div>
        </div>

        <h4 class="section-title"><i class="fas fa-history"></i> Línea de Tiempo de Consultas</h4>
        <div id="historyTimeline" class="history-timeline"></div>
    </div>
</aside>

<!-- MODAL EDITAR PROPIETARIO -->
<div id="modalEditarPropietario" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-edit"></i> Editar Propietario</h3>
            <span class="close" onclick="closeModal('modalEditarPropietario')">&times;</span>
        </div>
        <form id="formEditPropietario" onsubmit="updateOwner(event)">
            <input type="hidden" name="documento_original" id="edit_owner_doc_orig">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="input-group">
                        <label>N° Documento</label>
                        <input type="text" name="documento" id="edit_owner_doc" readonly>
                    </div>
                    <div class="input-group">
                        <label>Nombre Completo</label>
                        <input type="text" name="nombre_completo" id="edit_owner_nombre" required>
                    </div>
                    <div class="input-group">
                        <label>Teléfono</label>
                        <input type="text" name="telefono" id="edit_owner_tel" required>
                    </div>
                    <div class="input-group">
                        <label>Email</label>
                        <input type="email" name="email" id="edit_owner_email" required>
                    </div>
                    <div class="input-group full">
                        <label>Estado</label>
                        <select name="estado" id="edit_owner_estado">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-primary full-btn" style="margin-top: 2rem;">Actualizar Propietario</button>
            </div>
        </form>
    </div>
</div>

<!-- DRAWER REGISTRAR VACUNA -->
<div id="drawerVacunaOverlay" class="drawer-overlay" onclick="closeDrawer('drawerVacuna')"></div>
<aside id="drawerVacuna" class="drawer">
    <div class="drawer-header">
        <button type="button" class="drawer-close" onclick="closeDrawer('drawerVacuna')" aria-label="Cerrar">
            <i class="fas fa-times"></i>
        </button>
        <div class="drawer-title-wrap">
            <h2><i class="fas fa-syringe"></i> Registrar Vacunación</h2>
            <p id="vacunaPetName"></p>
        </div>
    </div>
    <form id="formVacuna" onsubmit="saveVaccine(event)">
        <input type="hidden" name="id_mascota" id="vacuna_id_mascota">
        <div class="drawer-body">
            <!-- Sección: Información de la Vacuna -->
            <div style="margin-bottom: 1.5rem;">
                <h4 style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Información de la Vacuna</h4>
                <div class="form-grid">
                    <div class="input-group full">
                        <label>Nombre de la Vacuna *</label>
                        <select name="nombre_vacuna" required id="vacunaSelect" onchange="toggleNuevaVacuna(this)">
                            <option value="">Cargando vacunas...</option>
                        </select>
                    </div>
                    <div class="input-group full" id="nuevaVacunaContainer" style="display: none;">
                        <label>Nueva Vacuna (si no está en la lista) *</label>
                        <input type="text" name="nueva_vacuna" id="nuevaVacunaInput" placeholder="Escribe el nombre de la nueva vacuna">
                        <small style="color: var(--text-muted); font-size: 0.75rem;">Esta vacuna se agregará al catálogo para futuros usos</small>
                    </div>
                    <div class="input-group">
                        <label>Laboratorio *</label>
                        <select name="laboratorio" required id="laboratorioSelect" onchange="toggleNuevoLaboratorio(this)">
                            <option value="">Seleccione laboratorio...</option>
                        </select>
                    </div>
                    <div class="input-group" id="nuevoLaboratorioContainer" style="display: none;">
                        <label>Nuevo Laboratorio (si no está en la lista) *</label>
                        <input type="text" name="nuevo_laboratorio" id="nuevoLaboratorioInput" placeholder="Escribe el nombre del nuevo laboratorio">
                        <small style="color: var(--text-muted); font-size: 0.75rem;">Este laboratorio se agregará al catálogo para futuros usos</small>
                    </div>
                    <div class="input-group">
                        <label>Lote *</label>
                        <input type="text" name="lote" required placeholder="Ej. ABC123456" pattern="[A-Za-z0-9]+" title="Solo letras y números">
                    </div>
                </div>
            </div>

            <!-- Sección: Fechas -->
            <div style="margin-bottom: 1.5rem;">
                <h4 style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Fechas</h4>
                <div class="form-grid">
                    <div class="input-group">
                        <label>Fecha Aplicación *</label>
                        <input type="date" name="fecha_aplicacion" value="<?php echo date(
                            "Y-m-d",
                        ); ?>" required>
                    </div>
                    <div class="input-group">
                        <label>Próxima Dosis</label>
                        <input type="date" name="fecha_proxima">
                    </div>
                </div>
            </div>

            <!-- Sección: Observaciones -->
            <div>
                <h4 style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Observaciones</h4>
                <div class="input-group full">
                    <textarea name="observaciones" rows="2" placeholder="Notas adicionales sobre la vacunación..."></textarea>
                </div>
            </div>

            <button type="submit" class="btn-primary full-btn" style="margin-top: 1.5rem;">Guardar Registro</button>
        </div>
    </form>
</aside>

<!-- DRAWER REGISTRAR DESPARASITACIÓN -->
<div id="drawerDesparasitacionOverlay" class="drawer-overlay" onclick="closeDrawer('drawerDesparasitacion')"></div>
<aside id="drawerDesparasitacion" class="drawer">
    <div class="drawer-header">
        <button type="button" class="drawer-close" onclick="closeDrawer('drawerDesparasitacion')" aria-label="Cerrar">
            <i class="fas fa-times"></i>
        </button>
        <div class="drawer-title-wrap">
            <h2><i class="fas fa-bug"></i> Registrar Desparasitación</h2>
            <p id="despPetName"></p>
        </div>
    </div>
    <form id="formDesparasitacion" onsubmit="saveDeworming(event)">
        <input type="hidden" name="id_mascota" id="desp_id_mascota">
        <div class="drawer-body">
            <!-- Sección: Tipo y Producto -->
            <div style="margin-bottom: 1.5rem;">
                <h4 style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Tipo y Producto</h4>
                <div class="form-grid">
                    <div class="input-group">
                        <label>Tipo *</label>
                        <select name="tipo" required>
                            <option value="interna">Interna (Pastillas/Jarabe)</option>
                            <option value="externa">Externa (Pipeta/Collar)</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Producto *</label>
                        <select name="producto" required id="productoSelect" onchange="toggleNuevoProducto(this)">
                            <option value="">Seleccione producto...</option>
                        </select>
                    </div>
                    <div class="input-group full" id="nuevoProductoContainer" style="display: none;">
                        <label>Nuevo Producto (si no está en la lista) *</label>
                        <input type="text" name="nuevo_producto" id="nuevoProductoInput" placeholder="Escribe el nombre del nuevo producto">
                        <small style="color: var(--text-muted); font-size: 0.75rem;">Este producto se agregará al catálogo para futuros usos</small>
                    </div>
                </div>
            </div>

            <!-- Sección: Periodicidad y Fecha -->
            <div style="margin-bottom: 1.5rem;">
                <h4 style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Periodicidad y Fecha</h4>
                <div class="form-grid">
                    <div class="input-group">
                        <label>Periodicidad *</label>
                        <select name="periodicidad" required>
                            <option value="mensual">Mensual (1 mes)</option>
                            <option value="trimestral">Trimestral (3 meses)</option>
                            <option value="semestral">Semestral (6 meses)</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Fecha Aplicación *</label>
                        <input type="date" name="fecha_aplicacion" value="<?php echo date(
                            "Y-m-d",
                        ); ?>" required>
                    </div>
                </div>
                <div style="background: #f0fdf4; padding: 12px; border-radius: 8px; margin-top: 10px; font-size: 0.8rem; color: #166534; border: 1px solid #bbf7d0;">
                    <i class="fas fa-info-circle"></i> La fecha de la próxima dosis se calculará automáticamente según la periodicidad.
                </div>
            </div>

            <!-- Sección: Observaciones -->
            <div>
                <h4 style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Observaciones</h4>
                <div class="input-group full">
                    <textarea name="observaciones" rows="2" placeholder="Notas adicionales sobre la desparasitación..."></textarea>
                </div>
            </div>

            <button type="submit" class="btn-primary full-btn" style="margin-top: 1.5rem;">Guardar Registro</button>
        </div>
    </form>
</aside>

<!-- MODAL AGENDAR CITA -->
<div id="modalCita" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="far fa-calendar-check"></i> Agendar Cita</h3>
            <span class="close" onclick="closeModal('modalCita')">&times;</span>
        </div>
        <form id="formCita" onsubmit="saveAppointment(event)">
            <input type="hidden" name="id_mascota" id="cita_id_mascota">
            <div class="modal-body">
                <p class="sub-text">Agendando cita para: <strong id="citaPetName"></strong></p>
                <div class="form-grid">
                    <div class="input-group">
                        <label>Fecha *</label>
                        <input type="date" name="fecha" id="cita_fecha" min="<?php echo date(
                            "Y-m-d",
                        ); ?>" required>
                    </div>
                    <div class="input-group">
                        <label>Hora *</label>
                        <input type="time" name="hora" required>
                    </div>
                    <div class="input-group full">
                        <label>Veterinario Asignado *</label>
                        <select name="doc_veterinario" id="cita_veterinario" required>
                            <option value="">Cargando veterinarios...</option>
                        </select>
                    </div>
                    <div class="input-group full">
                        <label>Motivo de la Cita *</label>
                        <input type="text" name="motivo" required placeholder="Ej. Chequeo general, Vacunación, Enfermedad...">
                    </div>
                </div>
                <div style="background: #f0fdf4; padding: 10px; border-radius: 8px; margin-top: 10px; font-size: 0.85rem; color: #16a34a;">
                    <i class="fas fa-envelope"></i> Se enviará un correo de confirmación al propietario.
                </div>
                <button type="submit" class="btn-primary full-btn" style="margin-top: 1.5rem;">Confirmar Cita</button>
            </div>
        </form>
    </div>
</div>

<div id="lightboxVisor" class="lightbox" onclick="closeLightbox()">
    <img src="" alt="Vista previa">
</div>
