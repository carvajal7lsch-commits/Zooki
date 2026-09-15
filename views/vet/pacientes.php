<?php
// views/mascotas/listado.php
?>
<!-- intl-tel-input CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.10/build/css/intlTelInput.css">
<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<div class="section-card">
    <!-- Super Header Compacto -->
    <div class="header-container-white">
        <!-- Area: desc -->
        <div class="head-title-desc">
            <h1 class="users-page-title"><i class="fas fa-paw icon-primary"></i> Gestión de Pacientes</h1>
            <p class="users-module-desc">
                Administra el directorio de clientes y sus mascotas, y gestiona sus expedientes clínicos de manera integrada.
            </p>
        </div>

        <!-- Area: top -->
        <div class="head-top-right">
            <div class="tabs-wrapper">
                <button onclick="switchModule('owners')" id="tabOwners" class="tab-btn active">
                    <i class="fas fa-users"></i>
                    <span>Propietarios</span>
                </button>
                <button onclick="switchModule('pets')" id="tabPets" class="tab-btn">
                    <i class="fas fa-paw"></i>
                    <span>Mascotas</span>
                </button>
            </div>
            
            <div class="command-center-actions cc-actions-gap">
                <button onclick="abrirModalRegistro('propietario')" class="btn-create">
                    <i class="fas fa-plus-circle"></i>
                    <span>Nuevo Registro</span>
                </button>
            </div>
        </div>

        <!-- Area: search and status (Filtros Propietarios) -->
        <div class="users-controls-bar__filters is-active" id="searchRowOwners">
            <div class="head-search">
                <div class="search-input">
                    <i class="fas fa-search"></i>
                    <input type="text" id="ownerSearch" placeholder="Buscar propietario por nombre, documento o email..." onkeyup="filterOwners()">
                </div>
            </div>
            <div class="head-status-filters">
                <div class="segmented-control" id="filterEstadoPropietarios">
                    <input type="radio" name="estado_propietarios" id="eprop_todos" value="" checked onchange="filterOwners()">
                    <label for="eprop_todos">Todos</label>
                    <input type="radio" name="estado_propietarios" id="eprop_activos" value="1" onchange="filterOwners()">
                    <label for="eprop_activos">Activos</label>
                    <input type="radio" name="estado_propietarios" id="eprop_inactivos" value="0" onchange="filterOwners()">
                    <label for="eprop_inactivos">Inactivos</label>
                </div>
            </div>
        </div>

        <!-- Area: search and status (Filtros Mascotas) -->
        <div class="users-controls-bar__filters" id="searchRowPets" style="display: none;">
            <div class="head-search">
                <div class="search-input">
                    <i class="fas fa-search"></i>
                    <input type="text" id="tableSearch" placeholder="Buscar por nombre, dueño o HC..." onkeyup="filterTable()">
                </div>
            </div>
            <div class="head-status-filters">
                <select onchange="filterTable()" class="command-center-filter-select filter-select">
                    <option value="">Todas las especies</option>
                    <option value="Canino">Caninos</option>
                    <option value="Felino">Felinos</option>
                </select>
                <div class="segmented-control" id="filterEstadoMascotas">
                    <input type="radio" name="estado_mascotas" id="emasc_todos" value="" checked onchange="filterTable()">
                    <label for="emasc_todos">Todos</label>
                    <input type="radio" name="estado_mascotas" id="emasc_activos" value="1" onchange="filterTable()">
                    <label for="emasc_activos">Activos</label>
                    <input type="radio" name="estado_mascotas" id="emasc_inactivos" value="0" onchange="filterTable()">
                    <label for="emasc_inactivos">Inactivos</label>
                </div>
            </div>
        </div>

        <!-- Area: view (View Toggle) -->
        <div class="head-view-toggle">
            <!-- Selector de vistas para Propietarios -->
            <div class="view-toggle" id="ownerViewToggle" role="group" aria-label="Tipo de vista">
                <button type="button" class="view-toggle-btn" onclick="switchOwnerView('list')" id="btnOwnerViewList" title="Vista tabla">
                    <i class="bi-table"></i>
                </button>
                <button type="button" class="view-toggle-btn active" onclick="switchOwnerView('grid')" id="btnOwnerViewGrid" title="Vista cards">
                    <i class="bi-grid"></i>
                </button>
            </div>
            <!-- Selector de vistas para Mascotas -->
            <div class="view-toggle" id="petViewToggle" role="group" aria-label="Tipo de vista" style="display: none;">
                <button type="button" class="view-toggle-btn" onclick="switchView('list')" id="btnViewList" title="Vista tabla">
                    <i class="bi-table"></i>
                </button>
                <button type="button" class="view-toggle-btn active" onclick="switchView('grid')" id="btnViewGrid" title="Vista cards">
                    <i class="bi-grid"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- CONTENEDOR PRINCIPAL DEL MÓDULO -->
    <div id="moduleOwners" class="module-section active">

        <!-- VISTA 1: Directorio de Clientes -->
        <div id="ownersDirectory" class="view-container active">

            <div id="ownerListView" class="view-container">
                <div class="table-container">
                    <table class="modern-table" id="ownersTable">
                        <thead>
                            <tr>
                                <th>Documento</th>
                                <th>Nombre Completo</th>
                                <th>Contacto</th>
                                <th>Email</th>
                                <th>Mascotas</th>
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

            <div id="ownerGridView" class="view-container active">
                <div class="pets-grid" id="ownersGrid">
                    <!-- Se cargará por AJAX -->
                </div>
            </div>
        </div>

        <!-- VISTA 2: Expediente Detallado (Dossier) - Se muestra al elegir un cliente -->
        <div id="dossierView" class="view-container dossier-view-active dossier-animation d-none">

            <!-- CONTENEDOR 1: LISTADO DE PACIENTES (Layout Rediseñado) -->
            <div id="dossierListContainer" class="dossier-layout-grid">

                <!-- Columna Izquierda: Datos Propietario y Acciones -->
                <div class="dossier-sidebar">
                    <!-- Tarjeta del Propietario -->
                    <div class="dossier-card owner-details-card">
                        <div class="dossier-card-header">
                            <div class="dossier-card-title">
                                <div class="icon-circle"><i class="fas fa-user-circle"></i></div>
                                <h3>Propietario</h3>
                            </div>
                            <div class="dossier-card-actions">
                                <button onclick="editOwnerFromDossier()" class="btn-icon-light" title="Editar Propietario">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button onclick="hideDossier()" class="btn-icon-light" title="Volver al Directorio">
                                    <i class="fas fa-arrow-left"></i>
                                </button>
                            </div>
                        </div>

                        <div class="owner-data-list">
                            <div class="data-group">
                                <label>NOMBRE COMPLETO</label>
                                <span id="dossierOwnerName" class="data-value main-value">---</span>
                            </div>
                            <div class="data-group">
                                <label>DOCUMENTO DE IDENTIDAD</label>
                                <span id="dossierOwnerDoc" class="data-value">---</span>
                            </div>
                            <div class="data-group">
                                <label>TELÉFONO DE CONTACTO</label>
                                <span id="dossierOwnerPhone" class="data-value">---</span>
                            </div>
                            <div class="data-group">
                                <label>CORREO ELECTRÓNICO</label>
                                <span id="dossierOwnerEmail" class="data-value">---</span>
                            </div>
                            <div class="data-group horizontal-group">
                                <label>ESTADO</label>
                                <span id="dossierOwnerEstado" class="status-badge">---</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta de Acciones Rápidas -->
                    <div class="dossier-card quick-actions-card">
                        <div class="quick-actions-header">
                            <i class="fas fa-bolt"></i> ACCIONES RÁPIDAS
                        </div>
                        <div class="quick-actions-list">
                            <button onclick="window.location.href='tel:'+document.getElementById('dossierOwnerPhone').innerText" class="btn-quick-action">
                                <span><i class="fas fa-phone-alt"></i> Llamar Propietario</span>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                            <button onclick="window.location.href='mailto:'+document.getElementById('dossierOwnerEmail').innerText" class="btn-quick-action">
                                <span><i class="fas fa-envelope"></i> Enviar Email</span>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                            <button onclick="window.location.href='index.php?action=vet_agenda'" class="btn-quick-action">
                                <span><i class="far fa-calendar-alt"></i> Agendar Cita</span>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Contenido Principal (Listado o Dashboard) -->
                <div class="dossier-main-content">
                    
                    <!-- SECCIÓN A: LISTADO DE PACIENTES -->
                    <div class="dossier-card patients-card" id="dossierListSection">
                        <div class="patients-header-row">
                            <div class="patients-title-area">
                                <h2><i class="fas fa-paw"></i> Pacientes</h2>
                                <button onclick="addNewPetFromDossier()" class="btn-pill-primary">
                                    <i class="fas fa-plus"></i> Nuevo
                                </button>
                            </div>
                            <div class="patients-filters-area">
                                <div class="search-box-inline">
                                    <i class="fas fa-search"></i>
                                    <input type="text" id="dossierPetSearch" placeholder="Buscar paciente..." onkeyup="filterDossierPets()">
                                </div>
                                <select id="dossierPetSpeciesFilter" onchange="filterDossierPets()">
                                    <option value="">Todas las especies</option>
                                    <option value="Canino">Caninos</option>
                                    <option value="Felino">Felinos</option>
                                    <option value="Roedor">Roedores</option>
                                    <option value="Ave">Aves</option>
                                </select>
                            </div>
                        </div>

                        <!-- Carrusel de mascotas: las flechas van sobre las tarjetas y
                             solo aparecen cuando hay más mascotas hacia ese lado. -->
                        <div class="pets-carousel">
                            <button type="button" class="pets-carousel__nav pets-carousel__nav--prev" id="dossierPetsPrev" onclick="moverCarruselMascotas(-1)" aria-label="Mascotas anteriores" hidden><i class="fas fa-chevron-left"></i></button>
                            <div class="dossier-pets-grid" id="dossierPetsScroll">
                                <!-- Cards de mascotas se cargarán vía JS -->
                            </div>
                            <button type="button" class="pets-carousel__nav pets-carousel__nav--next" id="dossierPetsNext" onclick="moverCarruselMascotas(1)" aria-label="Más mascotas" hidden><i class="fas fa-chevron-right"></i></button>
                        </div>

                        <div class="patients-footer">
                            <span class="patients-count" id="dossierPetsCount">Mostrando 0 pacientes asociados</span>
                        </div>
                    </div>

                    <!-- SECCIÓN B: EXPEDIENTE DETALLADO DE MASCOTA (Dashboard) -->
                    <div class="dossier-card patients-card dossier-pet-dashboard-section" id="dossierPetDashboardSection">
                        <!-- Header del Dashboard -->
                        <div class="dossier-dashboard-header dossier-dashboard-header-styled">
                            <div class="dossier-dashboard-title">
                                <button onclick="showPetsListFromDossier()" class="command-center-btn-secondary btn-dossier-back">
                                    <i class="fas fa-arrow-left"></i> Volver a Pacientes
                                </button>
                                <h4><i class="fas fa-paw"></i> <span id="dashPetName"></span></h4>
                            </div>
                            <div class="dossier-dashboard-actions">
                                <button id="dashEditPetBtn" class="command-center-btn-secondary btn-dossier-back">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
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
                            <div id="dashGeneral" class="dash-tab-content active d-block">
                                <div class="dossier-pet-info-grid">
                                    <!-- Foto del paciente -->
                                    <div class="pos-relative">
                                        <img id="dashPetPhoto" src="img/default-pet.svg" class="dossier-pet-photo" alt="">
                                        <div id="dashPetStatus" class="dash-pet-status"></div>
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
                                        <div class="dossier-pet-field pet-colores-wrapper">
                                            <label>Colores Base</label>
                                            <div id="dashPetColores" class="dash-pet-colores"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- PESTAÑA: HISTORIA CLÍNICA UNIFICADA -->
                            <div id="dashHistorial" class="dash-tab-content d-none">
                                <div class="dash-historial-header">
                                    <h5 class="dash-historial-title">
                                        <i class="fas fa-notes-medical icon-primary"></i> Línea de Tiempo Clínica
                                    </h5>
                                    <button onclick="printMedicalHistory(document.getElementById('dashEditPetBtn').getAttribute('data-id'), document.getElementById('dashPetName').innerText)" class="btn-outline btn-print-history">
                                        <i class="fas fa-print"></i> Historial Completo
                                    </button>
                                </div>
                                <div id="dashHistorialTimeline" class="dash-historial-timeline">
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

        <div id="listView" class="view-container">
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
                                <tr data-id="<?php echo $m["id_mascota"]; ?>" data-estado="<?php echo $m["estado"]; ?>">
                                    <td>
                                        <img src="<?php echo htmlspecialchars($m["url_foto"] ? "uploads/mascotas/" . rawurlencode($m["url_foto"]) : "img/default-pet.svg"); ?>"
                                             class="table-thumb"
                                             onclick="viewImage(this.src)"
                                             onerror="this.onerror=null;this.src='img/default-pet.svg'">
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
                                            <?php // RN-102: sin HC (aún no tiene consultas) no hay historial que abrir. ?>
                                            <?php if (!empty($m['numero_historia_clinica'])): ?>
                                            <button onclick="viewMedicalHistory(<?php echo (int) $m['id_mascota']; ?>, <?php echo htmlspecialchars(json_encode($m['nombre']), ENT_QUOTES); ?>)" class="btn-icon history" title="Ver Historial Clínico">
                                                <i class="fas fa-notes-medical"></i>
                                            </button>
                                            <?php endif; ?>

                                            <button onclick="editPet(<?php echo $m['id_mascota']; ?>)" class="btn-icon edit" title="Editar Mascota">
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
        <div id="gridView" class="view-container active">
            <div class="pets-grid" id="petsGrid">
                <?php foreach ($mascotas as $m): ?>
                    <div class="client-card person-card pet-card" data-id="<?php echo $m["id_mascota"]; ?>" data-species="<?php echo $m["nombre_especie"]; ?>" data-estado="<?php echo $m["estado"]; ?>">
                        <div class="card-header-mini">
                            <div class="avatar-mini cursor-pointer" onclick="viewPetInDossier(<?php echo htmlspecialchars(json_encode($m['doc_propietario']), ENT_QUOTES); ?>, <?php echo (int) $m['id_mascota']; ?>)">
                                <img src="<?php echo htmlspecialchars($m["url_foto"] ? "uploads/mascotas/" . rawurlencode($m["url_foto"]) : "img/default-pet.svg"); ?>"
                                     alt="<?php echo htmlspecialchars($m["nombre"]); ?>"
                                     onerror="this.onerror=null;this.src='img/default-pet.svg'">
                            </div>
                            <div class="status-indicator">
                                <label class="toggle-switch" title="<?php echo $m['estado'] == 1 ? 'Mascota Activa (Clic para desactivar)' : 'Mascota Inactiva (Clic para activar)'; ?>">
                                    <input type="checkbox" <?php echo $m['estado'] == 1 ? 'checked' : ''; ?> onchange="togglePetStatus(<?php echo $m['id_mascota']; ?>, this.checked ? 1 : 0)">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        <div class="card-body-mini cursor-pointer" onclick="viewPetInDossier(<?php echo htmlspecialchars(json_encode($m['doc_propietario']), ENT_QUOTES); ?>, <?php echo (int) $m['id_mascota']; ?>)">
                            <h3 class="card-title-mini"><?php echo htmlspecialchars($m["nombre"]); ?></h3>
                            <div class="card-tags-mini">
                                <?php if($m['estado'] == 0): ?>
                                    <span class="tag-mini"><i class="bi bi-moon-stars"></i> Inactivo</span>
                                <?php endif; ?>
                                <span class="tag-mini"><i class="fas fa-paw"></i> <?php echo htmlspecialchars($m["nombre_especie"]); ?> • <?php echo htmlspecialchars($m["nombre_raza"] ?? 'Sin Raza'); ?></span>
                                <span class="tag-mini"><i class="fas fa-venus-mars"></i> <?php echo htmlspecialchars($m["sexo"]); ?></span>
                            </div>
                            <div class="card-contact-mini">
                                <span class="contact-text-mini"><i class="bi bi-person"></i> <?php echo htmlspecialchars($m["propietario_nombre"]); ?></span>
                                <span class="contact-text-mini"><i class="bi bi-file-medical"></i> HC: <?php echo htmlspecialchars($m["numero_historia_clinica"] ?: 'Sin asignar'); ?></span>
                            </div>
                        </div>
                        <div class="card-footer-mini">
                            <button class="action-btn-mini" onclick="editPet(<?php echo $m['id_mascota']; ?>)" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            <?php if (!empty($m['numero_historia_clinica'])): ?>
                            <button class="action-btn-mini" onclick="viewMedicalHistory(<?php echo (int) $m['id_mascota']; ?>, <?php echo htmlspecialchars(json_encode($m['nombre']), ENT_QUOTES); ?>)" title="Ver Historial Clínico">
                                <i class="bi bi-file-earmark-medical-fill"></i>
                            </button>
                            <?php endif; ?>
                            <button class="action-btn-mini" onclick="viewPetInDossier(<?php echo htmlspecialchars(json_encode($m['doc_propietario']), ENT_QUOTES); ?>, <?php echo (int) $m['id_mascota']; ?>)" title="Ver Expediente">
                                <i class="bi bi-eye-fill"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>


<!-- MODAL NUEVO REGISTRO UNIFICADO -->
<div id="modalNuevoRegistro" class="users-modal d-none close-modal-backdrop" data-modal="modalNuevoRegistro">
    <div class="modal-content users-modal__panel modal-lg">
        <div class="modal-header">
            <div class="header-left-group">
                <h3><i class="fas fa-folder-plus"></i> Nuevo Registro</h3>
                <div class="premium-tabs">
                    <button type="button" class="premium-tab-btn active modal-tab-btn" data-target-tab="tabNuevoPropietario">
                        <i class="fas fa-user"></i> Propietario
                    </button>
                    <button type="button" class="premium-tab-btn modal-tab-btn" data-target-tab="tabNuevaMascota">
                        <i class="fas fa-paw"></i> Mascota
                    </button>
                </div>
            </div>
            <button type="button" class="close-modal close-modal-btn" data-modal="modalNuevoRegistro" aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="modal-body-tabs">
            <!-- TAB PROPIETARIO -->
            <div id="tabNuevoPropietario" class="modal-tab-content active">
                <form id="formPropietario" onsubmit="saveOwner(event)">
                    <div class="users-modal__body">
                        <div class="form-grid">
                            <div class="input-group">
                                <label>Tipo Documento</label>
                                <div class="input-wrapper">
                                    <i class="far fa-id-card field-icon"></i>
                                    <select name="tipo_documento" id="new_owner_tipo_doc" required class="validate-select">
                                        <option value="CC" selected>Cédula de Ciudadanía</option>
                                        <option value="TI">Tarjeta de Identidad</option>
                                        <option value="CE">Cédula de Extranjería</option>
                                        <option value="PP">Pasaporte</option>
                                    </select>
                                </div>
                                <span class="error-message display-none-error"></span>
                            </div>
                            <div class="input-group">
                                <label>N° Documento</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-hashtag field-icon"></i>
                                    <input type="text" name="documento" id="new_owner_doc" required placeholder="Ej: 1023456789" maxlength="20" class="validate-doc">
                                </div>
                                <span class="error-message display-none-error"></span>
                            </div>
                            <div class="input-group">
                                <label>Nombre Completo</label>
                                <div class="input-wrapper">
                                    <i class="far fa-user field-icon"></i>
                                    <input type="text" name="nombre_completo" id="new_owner_nombre" required placeholder="Nombres y apellidos" maxlength="100" class="validate-name">
                                </div>
                                <span class="error-message display-none-error"></span>
                            </div>
                            <div class="input-group">
                                <label>Teléfono</label>
                                <div class="input-wrapper no-icon">
                                    <input type="text" name="telefono" id="new_owner_tel" required placeholder="Ej: 3001234567" maxlength="15" class="validate-tel">
                                </div>
                                <span class="error-message display-none-error"></span>
                            </div>
                            <div class="input-group">
                                <label>Email</label>
                                <div class="input-wrapper">
                                    <i class="far fa-envelope field-icon"></i>
                                    <input type="email" name="email" id="new_owner_email" required placeholder="correo@ejemplo.com" maxlength="100" class="validate-email">
                                </div>
                                <span class="error-message display-none-error"></span>
                            </div>
                            <div class="input-group">
                                <label>Estado del Propietario</label>
                                <div class="flex-center-gap mt-2">
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="toggle_owner_estado" checked class="status-toggle-input" data-target="new_owner_estado" data-text-target="text_owner_estado" data-text-active="Activo en el sistema" data-text-inactive="Inactivo">
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <span id="text_owner_estado" class="text-success" style="font-size: 0.9rem; font-weight: 500;">Activo en el sistema</span>
                                </div>
                                <input type="hidden" name="estado" id="new_owner_estado" value="1">
                                <span class="error-message display-none-error"></span>
                            </div>
                        </div>
                    </div>
                    <div class="users-modal__footer">
                        <button type="button" class="btn-modal-secondary close-modal-btn" data-modal="modalNuevoRegistro">Cancelar</button>
                        <button type="submit" class="btn-modal-primary">
                            <i class="fas fa-check-circle"></i> Registrar Propietario
                        </button>
                    </div>
                </form>
            </div>

            <!-- TAB MASCOTA -->
            <div id="tabNuevaMascota" class="modal-tab-content">
                <form id="formMascota" onsubmit="savePet(event)" enctype="multipart/form-data">
                    <div class="users-modal__body">
                        <div class="form-grid-split">
                            <div class="photo-side">
                                <div class="preview-box">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Subir Foto</span>
                                    <img id="newPreview" src="" class="d-none">
                                    <button type="button" class="btn-clear-img clear-preview-btn d-none" id="btnClearNewImg" data-input="newFoto" data-preview="newPreview" title="Quitar imagen"><i class="fas fa-times"></i></button>
                                </div>
                                <input type="file" name="foto" id="newFoto" accept="image/*" class="d-none image-upload-input" data-preview="newPreview" data-clear-btn="btnClearNewImg">

                                <div class="pet-extra-panel mt-1rem">
                                    <div class="input-group">
                                        <label class="m-0"><i class="fas fa-palette"></i> Colores Base</label>
                                        <select id="newSelectedColoresInput" name="colores[]" style="width: 100%; margin-top: 5px;">
                                            <!-- Cargado vía JS -->
                                        </select>
                                    </div>
                                    <div class="input-group mt-1rem">
                                        <label><i class="fas fa-toggle-on"></i> Estado de la Mascota</label>
                                        <div class="flex-center-gap mt-2">
                                            <label class="toggle-switch">
                                                <input type="checkbox" id="toggle_pet_estado" checked class="status-toggle-input" data-target="new_estado" data-text-target="text_pet_estado" data-text-active="Activo" data-text-inactive="Inactivo">
                                                <span class="toggle-slider"></span>
                                            </label>
                                            <span id="text_pet_estado" class="text-success" style="font-size: 0.9rem; font-weight: 500;">Activo</span>
                                        </div>
                                        <input type="hidden" name="estado" id="new_estado" value="1">
                                    </div>
                                </div>
                            </div>
                            <div class="data-side">
                                <div class="form-grid-pet form-col-gap">
                                    <div class="form-grid-2-gap">
                                        <div class="input-group">
                                            <label>Nombre de la Mascota</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-paw field-icon"></i>
                                                <input type="text" name="nombre" id="new_nombre" required placeholder="Ej: Firulais" maxlength="50">
                                            </div>
                                        </div>
                                        
                                        <div class="input-group">
                                            <label>Sexo</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-venus-mars field-icon"></i>
                                                <select name="sexo" id="new_sexo" required>
                                                    <option value="Macho">Macho</option>
                                                    <option value="Hembra">Hembra</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-grid-2-gap">
                                        <div class="input-group">
                                            <label>Especie</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-cat field-icon"></i>
                                                <select name="especie" id="new_especie" required onchange="loadBreeds(this.value, 'new_raza')">
                                                    <option value="">Seleccione...</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="input-group">
                                            <label>Raza</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-dna field-icon"></i>
                                                <select name="raza" id="new_raza" required onchange="checkOtherBreed(this, 'newOtherBreedGroup')">
                                                    <option value="">Especie primero</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-grid-2-gap">
                                        <div class="input-group">
                                            <label>Fecha Nacimiento</label>
                                            <div class="input-wrapper">
                                                <i class="far fa-calendar-alt field-icon"></i>
                                                <input type="text" class="flatpickr-date" name="fecha_nacimiento" id="new_fecha_nacimiento" placeholder="Seleccione fecha...">
                                            </div>
                                        </div>
                                        
                                        <div class="input-group">
                                            <label>Peso (Kg)</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-weight field-icon"></i>
                                                <input type="number" step="0.01" name="peso" id="new_peso" required placeholder="Ej: 4.5" min="0.1" max="200" oninput="if(this.value > 200) this.value = 200; if(this.value.length > 5) this.value = this.value.slice(0,5);">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="input-group d-none" id="newOtherBreedGroup">
                                        <label>Especifique la Raza</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-tag field-icon"></i>
                                            <input type="text" name="nueva_raza" id="new_nueva_raza" placeholder="¿Qué raza es?">
                                        </div>
                                    </div>



                                    <div class="input-group rel-pos">
                                        <label>Vincular Propietario *</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-search field-icon"></i>
                                            <input type="text" id="ownerSearchInput" placeholder="Buscar por nombre o documento..." onkeyup="searchOwnersForPet(this.value)" autocomplete="off">
                                        </div>
                                        <input type="hidden" name="doc_propietario" id="petOwnerDoc" required>
                                        <div id="ownerSuggestions" class="suggestions-list"></div>
                                        <div id="selectedOwnerInfo" class="selected-badge d-none">
                                            <i class="fas fa-user-check"></i> <span id="selectedOwnerName"></span>
                                            <i class="fas fa-times-circle close-selected-owner" onclick="clearOwnerSelection()"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="users-modal__footer">
                        <button type="button" class="btn-modal-secondary close-modal-btn" data-modal="modalNuevoRegistro">Cancelar</button>
                        <button type="submit" class="btn-modal-primary">
                            <i class="fas fa-check-circle"></i> Registrar Mascota
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="modalEditarMascota" class="users-modal d-none close-modal-backdrop" data-modal="modalEditarMascota">
    <div class="modal-content users-modal__panel modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Editar Mascota</h3>
            <button type="button" class="close-modal close-modal-btn" data-modal="modalEditarMascota" aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="formEditMascota">
            <input type="hidden" name="id_mascota" id="edit_id_mascota">
            <div class="users-modal__body">
                <div class="form-grid-split">
                    <div class="photo-side">
                        <div class="preview-box">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Cambiar Foto</span>
                            <img id="editPreview" src="" class="d-none">
                            <button type="button" class="btn-clear-img clear-preview-btn d-none" id="btnClearEditImg" data-input="editFoto" data-preview="editPreview" title="Quitar imagen"><i class="fas fa-times"></i></button>
                        </div>
                        <input type="file" name="foto" id="editFoto" accept="image/*" class="d-none image-upload-input" data-preview="editPreview" data-clear-btn="btnClearEditImg">

                        <div class="pet-extra-panel mt-1rem">
                            <div class="input-group">
                                <label class="m-0"><i class="fas fa-palette"></i> Colores Base</label>
                                <select id="editSelectedColoresInput" name="colores[]" class="w-100 mt-2">
                                    <!-- Cargado vía JS -->
                                </select>
                            </div>
                            <div class="input-group mt-1rem">
                                <label><i class="fas fa-toggle-on"></i> Estado de la Mascota</label>
                                <div class="flex-center-gap mt-2">
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="toggle_edit_pet_estado" class="status-toggle-input" data-target="edit_estado" data-text-target="text_edit_pet_estado" data-text-active="Activo" data-text-inactive="Inactivo">
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <span id="text_edit_pet_estado" class="text-success" style="font-size: 0.9rem; font-weight: 500;">Activo</span>
                                </div>
                                <input type="hidden" name="estado" id="edit_estado" value="1">
                            </div>
                        </div>
                    </div>
                    <div class="data-side">
                        <div class="form-grid-pet form-col-gap">
                            <div class="form-grid-2-gap">
                                <div class="input-group">
                                    <label>Nombre de la Mascota</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-paw field-icon"></i>
                                        <input type="text" name="nombre" id="edit_nombre" required maxlength="50" class="validate-name">
                                    </div>
                                    <span class="error-message display-none-error"></span>
                                </div>
                                
                                <div class="input-group">
                                    <label>Sexo</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-venus-mars field-icon"></i>
                                        <select name="sexo" id="edit_sexo" required>
                                            <option value="Macho">Macho</option>
                                            <option value="Hembra">Hembra</option>
                                            <option value="Desconocido">Desconocido</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-grid-2-gap">
                                <div class="input-group">
                                    <label>Especie</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-cat field-icon"></i>
                                        <select name="especie" id="edit_especie" required class="load-breeds-select" data-target="edit_raza">
                                            <option value="">Seleccione...</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="input-group">
                                    <label>Raza</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-dna field-icon"></i>
                                        <select name="raza" id="edit_raza" required class="check-other-breed" data-target="editOtherBreedGroup">
                                            <option value="">Seleccione especie...</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-grid-2-gap">
                                <div class="input-group">
                                    <label>Fecha Nacimiento</label>
                                    <div class="input-wrapper">
                                        <i class="far fa-calendar-alt field-icon"></i>
                                        <input type="text" class="flatpickr-date" name="fecha_nacimiento" id="edit_fecha_nac" placeholder="Seleccione fecha..." required>
                                    </div>
                                </div>
                                
                                <div class="input-group">
                                    <label>Peso (Kg)</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-weight field-icon"></i>
                                        <input type="number" step="0.01" name="peso" id="edit_peso" required placeholder="0.00">
                                    </div>
                                </div>
                            </div>

                            <!-- OTRA RAZA -->
                            <div class="form-grid-1-gap d-none" id="editOtherBreedGroup">
                                <div class="input-group">
                                    <label>Otra raza o especie <span style="color:#ef4444">*</span></label>
                                    <div class="input-wrapper no-icon">
                                        <input type="text" name="nueva_raza" id="edit_nueva_raza" placeholder="¿Qué raza es?">
                                    </div>
                                </div>
                            </div>

                            <!-- CAMBIAR PROPIETARIO -->
                            <div class="input-group rel-pos">
                                <label>Cambiar Propietario (Opcional)</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-user-edit field-icon"></i>
                                    <input type="text" id="editOwnerSearchInput" class="owner-search-input" placeholder="Buscar nuevo dueño..." autocomplete="off">
                                </div>
                                <input type="hidden" name="doc_propietario" id="edit_petOwnerDoc">
                                <div id="editOwnerSuggestions" class="suggestions-list"></div>
                                <div id="selectedEditOwnerInfo" class="selected-badge d-none">
                                    <div class="flex-center-gap">
                                        <i class="fas fa-user-check"></i> <span id="selectedEditOwnerName"></span>
                                    </div>
                                    <i class="fas fa-times-circle close-selected-owner"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="users-modal__footer">
                <button type="button" class="btn-modal-secondary close-modal-btn" data-modal="modalEditarMascota">Cancelar</button>
                <button type="submit" class="btn-modal-primary">Actualizar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL NUEVA CONSULTA -->
<?php include __DIR__ . "/modal_consulta.php"; ?>

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
<div id="modalEditarPropietario" class="users-modal d-none close-modal-backdrop" data-modal="modalEditarPropietario">
    <div class="modal-content users-modal__panel modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-user-edit"></i> Editar Propietario</h3>
            <button type="button" class="close-modal close-modal-btn" data-modal="modalEditarPropietario" aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="formEditPropietario">
            <input type="hidden" name="documento_original" id="edit_owner_doc_orig">
            <div class="users-modal__body">
                <div class="form-grid">
                    <div class="input-group">
                        <label>Tipo Documento</label>
                        <div class="input-wrapper">
                            <i class="far fa-id-card field-icon"></i>
                            <select name="tipo_documento" id="edit_owner_tipo_doc" required class="validate-select">
                                <option value="CC">Cédula de Ciudadanía</option>
                                <option value="TI">Tarjeta de Identidad</option>
                                <option value="CE">Cédula de Extranjería</option>
                                <option value="PP">Pasaporte</option>
                            </select>
                        </div>
                        <span class="error-message display-none-error"></span>
                    </div>
                    <div class="input-group">
                        <label>N° Documento</label>
                        <div class="input-wrapper">
                            <i class="fas fa-hashtag field-icon"></i>
                            <input type="text" name="documento" id="edit_owner_doc" required placeholder="Ej: 1023456789" maxlength="20" class="validate-doc input-readonly-locked" readonly tabindex="-1">
                        </div>
                        <span class="error-message display-none-error"></span>
                    </div>
                    <div class="input-group">
                        <label>Nombre Completo</label>
                        <div class="input-wrapper">
                            <i class="far fa-user field-icon"></i>
                            <input type="text" name="nombre_completo" id="edit_owner_nombre" required placeholder="Nombre y apellidos" maxlength="100" class="validate-name">
                        </div>
                        <span class="error-message display-none-error"></span>
                    </div>
                    <div class="input-group">
                        <label>Teléfono</label>
                        <div class="input-wrapper no-icon">
                            <input type="text" name="telefono" id="edit_owner_tel" required placeholder="Ej: 3001234567" maxlength="15" class="validate-tel">
                        </div>
                        <span class="error-message display-none-error"></span>
                    </div>
                    <div class="input-group">
                        <label>Email</label>
                        <div class="input-wrapper">
                            <i class="far fa-envelope field-icon"></i>
                            <input type="email" name="email" id="edit_owner_email" required placeholder="correo@ejemplo.com" maxlength="100" class="validate-email">
                        </div>
                        <span class="error-message display-none-error"></span>
                    </div>
                    <div class="input-group">
                        <label>Estado del Propietario</label>
                        <div class="flex-center-gap mt-2">
                            <label class="toggle-switch">
                                <input type="checkbox" id="toggle_edit_owner_estado" class="status-toggle-input" data-target="edit_owner_estado" data-text-target="text_edit_owner_estado" data-text-active="Activo en el sistema" data-text-inactive="Inactivo">
                                <span class="toggle-slider"></span>
                            </label>
                            <span id="text_edit_owner_estado" class="text-success" style="font-size: 0.9rem; font-weight: 500;">Activo en el sistema</span>
                        </div>
                        <input type="hidden" name="estado" id="edit_owner_estado" value="1">
                        <span class="error-message display-none-error"></span>
                    </div>
                </div>
            </div>
            <div class="users-modal__footer">
                <button type="button" class="btn-modal-secondary close-modal-btn" data-modal="modalEditarPropietario">Cancelar</button>
                <button type="submit" class="btn-modal-primary">Actualizar Propietario</button>
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

<!-- intl-tel-input JS -->
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.10/build/js/intlTelInput.min.js"></script>
<script>
let itiNewOwnerPhone;
let itiEditOwnerPhone;

document.addEventListener('DOMContentLoaded', function() {
    const newTelInput = document.querySelector("#new_owner_tel");
    if (newTelInput) {
        itiNewOwnerPhone = window.intlTelInput(newTelInput, {
            initialCountry: "co",
            preferredCountries: ["co", "us", "mx", "es"],
            nationalMode: false,
            autoInsertDialCode: true,
            strictMode: true,
            dropdownContainer: document.body,
            utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.10/build/js/utils.js"
        });
    }

    const editTelInput = document.querySelector("#edit_owner_tel");
    if (editTelInput) {
        itiEditOwnerPhone = window.intlTelInput(editTelInput, {
            initialCountry: "co",
            preferredCountries: ["co", "us", "mx", "es"],
            nationalMode: false,
            autoInsertDialCode: true,
            strictMode: true,
            dropdownContainer: document.body,
            utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.10/build/js/utils.js"
        });
    }
});

function checkOtherBreed(select, targetGroupId) {
    const group = document.getElementById(targetGroupId);
    if (!group) return;
    const input = group.querySelector('input');
    if (select.value === 'Otra' || select.value === 'other') {
        group.classList.remove('d-none');
        group.style.display = 'block';
        if (input) input.required = true;
    } else {
        group.classList.add('d-none');
        group.style.display = 'none';
        if (input) {
            input.required = false;
            input.value = '';
        }
    }
}

function validarSelect(select) {
    const errorSpan = select.closest('.input-group') ? select.closest('.input-group').querySelector('.error-message') : null;
    if (!errorSpan) return;
    
    if (select.value === '') {
        errorSpan.textContent = 'Debe seleccionar una opción';
        errorSpan.style.display = 'block';
        select.classList.add('error');
    } else {
        errorSpan.style.display = 'none';
        select.classList.remove('error');
    }
    
    if (select.name === 'tipo_documento') {
        const docInput = select.closest('form') ? select.closest('form').querySelector('input[name="documento"]') : null;
        if (docInput && docInput.value.length > 0) {
            validarDocumento(docInput);
        }
    }
}

async function verificarDocumentoExiste(input) {
    const value = input.value.replace(/[^0-9]/g, '');
    input.value = value;
    
    const errorSpan = input.closest('.input-group') ? input.closest('.input-group').querySelector('.error-message') : null;
    if (!errorSpan) return;
    
    if (value.length < 5) return;
    
    const originalDoc = document.getElementById('edit_owner_doc_orig') ? document.getElementById('edit_owner_doc_orig').value : '';
    const url = `index.php?action=verificar_documento_ajax&documento=${value}&exclude_doc=${originalDoc}`;
    
    try {
        const res = await fetch(url);
        const data = await res.json();
        
        if (data.exists) {
            errorSpan.textContent = 'El documento ya está registrado en el sistema';
            errorSpan.style.display = 'block';
            input.classList.add('error');
        } else {
            errorSpan.style.display = 'none';
            input.classList.remove('error');
        }
    } catch (e) {
        console.error('Error al verificar documento:', e);
    }
}

async function verificarEmailExiste(input) {
    const value = input.value;
    
    const errorSpan = input.closest('.input-group') ? input.closest('.input-group').querySelector('.error-message') : null;
    if (!errorSpan) return;
    
    if (!value || !value.includes('@')) return;
    
    const originalDoc = document.getElementById('edit_owner_doc_orig') ? document.getElementById('edit_owner_doc_orig').value : '';
    const url = `index.php?action=verificar_email_ajax&email=${encodeURIComponent(value)}&exclude_doc=${originalDoc}`;
    
    try {
        const res = await fetch(url);
        const data = await res.json();
        
        if (data.exists) {
            errorSpan.textContent = 'El correo electrónico ya está registrado en el sistema';
            errorSpan.style.display = 'block';
            input.classList.add('error');
        } else {
            errorSpan.style.display = 'none';
            input.classList.remove('error');
        }
    } catch (e) {
        console.error('Error al verificar email:', e);
    }
}

function validarDocumento(input) {
    const form = input.closest('form');
    let tipoDoc = '';
    if (form) {
        const select = form.querySelector('select[name="tipo_documento"]');
        if (select) tipoDoc = select.value;
    }

    let value = input.value;
    const errorSpan = input.closest('.input-group') ? input.closest('.input-group').querySelector('.error-message') : null;
    let errorMessage = '';

    if (tipoDoc === 'PP') {
        value = value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
        value = value.substring(0, 15);
        if (value.length > 0 && value.length < 6) errorMessage = 'El pasaporte debe tener al menos 6 caracteres';
    } else {
        value = value.replace(/[^0-9]/g, '');
        if (tipoDoc === 'CC') {
            value = value.substring(0, 10);
            if (value.length > 0 && value.length < 5) errorMessage = 'Cédula inválida (muy corta)';
            else if (value.length === 9) errorMessage = 'Las cédulas en Colombia no tienen 9 dígitos';
        } else if (tipoDoc === 'TI') {
            value = value.substring(0, 11);
            if (value.length > 0 && value.length < 10) errorMessage = 'La TI debe tener 10 u 11 dígitos';
        } else if (tipoDoc === 'CE') {
            value = value.substring(0, 7);
            if (value.length > 0 && value.length < 6) errorMessage = 'La CE debe tener al menos 6 dígitos';
        } else {
            value = value.substring(0, 20);
            if (value.length > 0 && value.length < 5) errorMessage = 'El documento debe tener al menos 5 dígitos';
        }
    }
    
    input.value = value;
    
    if (!errorSpan) return;
    
    if (errorMessage) {
        errorSpan.textContent = errorMessage;
        errorSpan.style.display = 'block';
        input.classList.add('error');
    } else {
        errorSpan.style.display = 'none';
        input.classList.remove('error');
    }
    
    if (value.length >= 5) {
        verificarDocumentoExiste(input);
    }
}

function validarNombre(input) {
    const value = input.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '');
    input.value = value;
    
    const errorSpan = input.closest('.input-group') ? input.closest('.input-group').querySelector('.error-message') : null;
    if (!errorSpan) return;
    
    if (value.length > 0 && value.length < 3) {
        errorSpan.textContent = 'El nombre debe tener al menos 3 caracteres';
        errorSpan.style.display = 'block';
        input.classList.add('error');
    } else if (value.length > 100) {
        errorSpan.textContent = 'El nombre no puede tener más de 100 caracteres';
        errorSpan.style.display = 'block';
        input.classList.add('error');
    } else {
        errorSpan.style.display = 'none';
        input.classList.remove('error');
    }
}

function validarEmail(input) {
    const value = input.value;
    
    const errorSpan = input.closest('.input-group') ? input.closest('.input-group').querySelector('.error-message') : null;
    if (!errorSpan) return;
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[a-zA-Z]{2,4}$/;
    
    if (value.length > 0 && !emailRegex.test(value)) {
        errorSpan.textContent = 'Ingrese un correo electrónico válido (ej. usuario@dominio.com)';
        errorSpan.style.display = 'block';
        input.classList.add('error');
    } else if (value.length > 100) {
        errorSpan.textContent = 'El correo no puede tener más de 100 caracteres';
        errorSpan.style.display = 'block';
        input.classList.add('error');
    } else {
        errorSpan.style.display = 'none';
        input.classList.remove('error');
    }
    
    if (emailRegex.test(value) && value.length > 0) {
        verificarEmailExiste(input);
    }
}

function validarTelefono(input) {
    const filteredValue = input.value.replace(/[^0-9+\s\-\(\)]/g, '');
    if (filteredValue !== input.value) {
        input.value = filteredValue;
    }
    
    const inputGroup = input.closest('.input-group');
    if (!inputGroup) return;
    
    const errorSpan = inputGroup.querySelector('.error-message');
    if (!errorSpan) return;
    
    if (input.value.trim() === '') {
        errorSpan.style.display = 'none';
        input.classList.remove('error');
        return;
    }
    
    const isNew = input.id === 'new_owner_tel';
    const iti = isNew ? itiNewOwnerPhone : itiEditOwnerPhone;
    
    if (iti) {
        if (iti.isValidNumber()) {
            errorSpan.style.display = 'none';
            input.classList.remove('error');
        } else {
            const errorMsgMap = ["Número inválido", "Código de país inválido", "Demasiado corto", "Demasiado largo", "Número inválido"];
            const errorCode = iti.getValidationError();
            const msg = (errorCode >= 0 && errorCode < errorMsgMap.length) ? errorMsgMap[errorCode] : "El número no es válido para este país";
            errorSpan.textContent = msg;
            errorSpan.style.display = 'block';
            input.classList.add('error');
        }
    }
}

async function saveOwner(e) {
    e.preventDefault();
    
    // Validar select tipo doc
    const tipoDoc = document.getElementById('new_owner_tipo_doc');
    if (tipoDoc) validarSelect(tipoDoc);
    
    // Validar doc
    const doc = document.getElementById('new_owner_doc');
    if (doc) validarDocumento(doc);
    
    // Validar nombre
    const nombre = document.getElementById('new_owner_nombre');
    if (nombre) validarNombre(nombre);
    
    // Validar tel
    const tel = document.getElementById('new_owner_tel');
    if (tel) validarTelefono(tel);
    
    // Validar email
    const email = document.getElementById('new_owner_email');
    if (email) validarEmail(email);
    
    // Check error spans
    const hasErrors = Array.from(e.target.querySelectorAll('.error-message')).some(el => el.style.display === 'block');
    if (hasErrors) {
        Swal.fire('Atención', 'Por favor, corrija los campos marcados en rojo antes de guardar.', 'warning');
        return;
    }
    
    const fd = new FormData(e.target);
    if (itiNewOwnerPhone && itiNewOwnerPhone.isValidNumber()) {
        fd.set('telefono', itiNewOwnerPhone.getNumber());
    }
    
    try {
        const res = await (await fetch('index.php?action=guardar_propietario_ajax', { method: 'POST', body: fd })).json();
        if (res.success) {
            Swal.fire('¡Listo!', 'Propietario registrado con éxito.', 'success');
            const input = document.getElementById('petOwnerDoc');
            if (input) {
                selectOwner({ documento: fd.get('documento'), nombre_completo: fd.get('nombre_completo') });
            }
            if (document.getElementById('modalNuevoRegistro')) {
                closeModal('modalNuevoRegistro');
            } else {
                closeModal('modalPropietario');
            }
            e.target.reset();
            if (itiNewOwnerPhone) itiNewOwnerPhone.setNumber('');
            loadOwners();
        } else {
            Swal.fire('Error', res.message || 'No se pudo registrar', 'error');
        }
    } catch (err) {
        console.error(err);
        Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
    }
}

async function updateOwner(e) {
    e.preventDefault();
    
    // Validar nombre
    const nombre = document.getElementById('edit_owner_nombre');
    if (nombre) validarNombre(nombre);
    
    // Validar tel
    const tel = document.getElementById('edit_owner_tel');
    if (tel) validarTelefono(tel);
    
    // Validar email
    const email = document.getElementById('edit_owner_email');
    if (email) validarEmail(email);
    
    // Check error spans
    const hasErrors = Array.from(e.target.querySelectorAll('.error-message')).some(el => el.style.display === 'block');
    if (hasErrors) {
        Swal.fire('Atención', 'Por favor, corrija los campos marcados en rojo antes de guardar.', 'warning');
        return;
    }
    
    const fd = new FormData(e.target);
    if (itiEditOwnerPhone && itiEditOwnerPhone.isValidNumber()) {
        fd.set('telefono', itiEditOwnerPhone.getNumber());
    }
    
    try {
        const res = await (await fetch('index.php?action=actualizar_propietario_ajax', { method: 'POST', body: fd })).json();
        if (res.success) {
            Swal.fire('¡Listo!', 'Datos del propietario actualizados correctamente.', 'success');
            closeModal('modalEditarPropietario');
            loadOwners();
            if (currentDossierDoc === fd.get('documento')) {
                openOwnerDossier(currentDossierDoc);
            }
        } else {
            Swal.fire('Error', res.message || 'No se pudo actualizar', 'error');
        }
    } catch (err) {
        console.error(err);
        Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
    }
}

// Search owners directory locally using real-time search
function filterOwners() {
    const term = document.getElementById('ownerSearch').value.toLowerCase();
    
    // Filter grid cards
    const cards = document.querySelectorAll('#ownerGridView .client-card');
    cards.forEach(card => {
        const txt = card.innerText.toLowerCase();
        card.style.display = txt.includes(term) ? '' : 'none';
    });
    
    // Filter table rows
    const rows = document.querySelectorAll('#ownersTable tbody tr');
    rows.forEach(row => {
        const txt = row.innerText.toLowerCase();
        row.style.display = txt.includes(term) ? '' : 'none';
    });
}
</script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>

