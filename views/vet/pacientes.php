<link rel="stylesheet" href="css/pacientes.css">
<?php
// views/mascotas/listado.php
// D1: los teléfonos usan la regla del servidor (ValidadorTelefono).
require_once __DIR__ . '/../../helpers/ValidadorTelefono.php';
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
                <button data-c3-click="evento0" id="tabOwners" class="tab-btn active">
                    <i class="fas fa-users"></i>
                    <span>Propietarios</span>
                </button>
                <button data-c3-click="evento1" id="tabPets" class="tab-btn">
                    <i class="fas fa-paw"></i>
                    <span>Mascotas</span>
                </button>
            </div>

            <div class="command-center-actions cc-actions-gap">
                <button data-c3-click="evento2" class="btn-create">
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
                    <input type="text" id="ownerSearch" placeholder="Buscar propietario por nombre, documento o email..." data-c3-keyup="evento3">
                </div>
            </div>
            <div class="head-status-filters">
                <div class="segmented-control" id="filterEstadoPropietarios">
                    <input type="radio" name="estado_propietarios" id="eprop_todos" value="" checked data-c3-change="evento4">
                    <label for="eprop_todos">Todos</label>
                    <input type="radio" name="estado_propietarios" id="eprop_activos" value="1" data-c3-change="evento5">
                    <label for="eprop_activos">Activos</label>
                    <input type="radio" name="estado_propietarios" id="eprop_inactivos" value="0" data-c3-change="evento6">
                    <label for="eprop_inactivos">Inactivos</label>
                </div>
            </div>
        </div>

        <!-- Area: search and status (Filtros Mascotas) -->
        <div class="pacientes-estilo-0 users-controls-bar__filters" id="searchRowPets">
            <div class="head-search">
                <div class="search-input">
                    <i class="fas fa-search"></i>
                    <input type="text" id="tableSearch" placeholder="Buscar por nombre, dueño o HC..." data-c3-keyup="evento7">
                </div>
            </div>
            <div class="head-status-filters">
                <select data-c3-change="evento8" class="command-center-filter-select filter-select">
                    <option value="">Todas las especies</option>
                    <option value="Canino">Caninos</option>
                    <option value="Felino">Felinos</option>
                </select>
                <div class="segmented-control" id="filterEstadoMascotas">
                    <input type="radio" name="estado_mascotas" id="emasc_todos" value="" data-c3-change="evento9">
                    <label for="emasc_todos">Todos</label>
                    <input type="radio" name="estado_mascotas" id="emasc_activos" value="1" checked data-c3-change="evento10">
                    <label for="emasc_activos">Activos</label>
                    <input type="radio" name="estado_mascotas" id="emasc_inactivos" value="0" data-c3-change="evento11">
                    <label for="emasc_inactivos">Inactivos</label>
                </div>
            </div>
        </div>

        <!-- Area: view (View Toggle) -->
        <div class="head-view-toggle">
            <!-- Selector de vistas para Propietarios -->
            <div class="view-toggle" id="ownerViewToggle" role="group" aria-label="Tipo de vista">
                <button type="button" class="view-toggle-btn" data-c3-click="evento12" id="btnOwnerViewList" title="Vista tabla">
                    <i class="bi-table"></i>
                </button>
                <button type="button" class="view-toggle-btn active" data-c3-click="evento13" id="btnOwnerViewGrid" title="Vista cards">
                    <i class="bi-grid"></i>
                </button>
            </div>
            <!-- Selector de vistas para Mascotas -->
            <div class="pacientes-estilo-1 view-toggle" id="petViewToggle" role="group" aria-label="Tipo de vista">
                <button type="button" class="view-toggle-btn" data-c3-click="evento14" id="btnViewList" title="Vista tabla">
                    <i class="bi-table"></i>
                </button>
                <button type="button" class="view-toggle-btn active" data-c3-click="evento15" id="btnViewGrid" title="Vista cards">
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
                                <button data-c3-click="evento16" class="btn-icon-light" title="Editar Propietario">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button data-c3-click="evento17" class="btn-icon-light" title="Volver al Directorio">
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
                            <button data-c3-click="evento18" class="btn-quick-action">
                                <span><i class="fas fa-phone-alt"></i> Llamar Propietario</span>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                            <button data-c3-click="evento19" class="btn-quick-action">
                                <span><i class="fas fa-envelope"></i> Enviar Email</span>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                            <button data-c3-click="evento20" class="btn-quick-action">
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
                                <button data-c3-click="evento21" class="btn-pill-primary">
                                    <i class="fas fa-plus"></i> Nuevo
                                </button>
                            </div>
                            <div class="patients-filters-area">
                                <div class="search-box-inline">
                                    <i class="fas fa-search"></i>
                                    <input type="text" id="dossierPetSearch" placeholder="Buscar paciente..." data-c3-keyup="evento22">
                                </div>
                                <select id="dossierPetSpeciesFilter" data-c3-change="evento23">
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
                            <button type="button" class="pets-carousel__nav pets-carousel__nav--prev" id="dossierPetsPrev" data-c3-click="evento24" aria-label="Mascotas anteriores" hidden><i class="fas fa-chevron-left"></i></button>
                            <div class="dossier-pets-grid" id="dossierPetsScroll">
                                <!-- Cards de mascotas se cargarán vía JS -->
                            </div>
                            <button type="button" class="pets-carousel__nav pets-carousel__nav--next" id="dossierPetsNext" data-c3-click="evento25" aria-label="Más mascotas" hidden><i class="fas fa-chevron-right"></i></button>
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
                                <button data-c3-click="evento26" class="command-center-btn-secondary btn-dossier-back">
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
                            <button class="dossier-dashboard-tab active" data-c3-click="evento27">
                                <i class="fas fa-info-circle"></i> Información General
                            </button>
                            <button class="dossier-dashboard-tab" data-c3-click="evento28">
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
                                    <button data-c3-click="evento29" class="btn-outline btn-print-history">
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
                                <tr data-id="<?php echo htmlspecialchars((string)($m["id_mascota"]), ENT_QUOTES, 'UTF-8'); ?>" data-estado="<?php echo htmlspecialchars((string)($m["estado"]), ENT_QUOTES, 'UTF-8'); ?>">
                                    <td>
                                        <img src="<?php echo htmlspecialchars($m["url_foto"] ? "uploads/mascotas/" . rawurlencode($m["url_foto"]) : "img/default-pet.svg"); ?>"
                                             class="table-thumb"
                                             data-c3-click="evento30"
                                             data-c3-error="evento31">
                                    </td>
                                    <td class="pet-name-cell">
                                        <div class="cell-info">
                                            <span class="main-text"><?php echo htmlspecialchars((string)($m[
                                                "nombre"
                                            ]), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="cell-info">
                                            <span class="main-text"><?php echo htmlspecialchars((string)($m[
                                                "nombre_especie"
                                            ]), ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars((string)($m[
     "nombre_raza"
 ] ?? "N/A"), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="sub-text"><?php echo htmlspecialchars((string)($m[
                                                "sexo"
                                            ]), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="cell-info">
                                            <span class="main-text"><?php echo htmlspecialchars((string)($m[
                                                "colores_nombres"
                                            ] ?? "N/A"), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </td>
                                    <td class="owner-name-cell">
                                        <span class="main-text"><?php echo htmlspecialchars((string)($m[
                                            "propietario_nombre"
                                        ]), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <small class="pacientes-estilo-2"><?php echo htmlspecialchars((string)($m[
                                            "propietario_documento"
                                        ]), ENT_QUOTES, 'UTF-8'); ?></small>
                                    </td>
                                    <td>
                                        <code class="hc-badge"><?php echo htmlspecialchars((string)($m[
                                            "numero_historia_clinica"
                                        ]), ENT_QUOTES, 'UTF-8'); ?></code>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo htmlspecialchars((string)($m[
                                            "estado"
                                        ] == 1
                                            ? "active"
                                            : "inactive"), ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars((string)($m["estado"] == 1
                                                ? "Activo"
                                                : "Inactivo"), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php // RN-102: sin HC (aún no tiene consultas) no hay historial que abrir. ?>
                                            <?php if (!empty($m['numero_historia_clinica'])): ?>
                                            <button data-c3-click="evento32" data-c3-arg0="<?php echo (int) $m['id_mascota']; ?>" data-c3-arg1="<?php echo htmlspecialchars(json_encode($m['nombre']), ENT_QUOTES); ?>" class="btn-icon history" title="Ver Historial Clínico">
                                                <i class="fas fa-notes-medical"></i>
                                            </button>
                                            <?php endif; ?>

                                            <button data-c3-click="evento33" data-c3-arg0="<?php echo htmlspecialchars((string)($m['id_mascota']), ENT_QUOTES, 'UTF-8'); ?>" class="btn-icon edit" title="Editar Mascota">
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
                    <div class="client-card person-card pet-card" data-id="<?php echo htmlspecialchars((string)($m["id_mascota"]), ENT_QUOTES, 'UTF-8'); ?>" data-species="<?php echo htmlspecialchars((string)($m["nombre_especie"]), ENT_QUOTES, 'UTF-8'); ?>" data-estado="<?php echo htmlspecialchars((string)($m["estado"]), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="card-header-mini">
                            <div class="avatar-mini cursor-pointer" data-c3-click="evento34" data-c3-arg0="<?php echo (int) $m['id_mascota']; ?>">
                                <img src="<?php echo htmlspecialchars($m["url_foto"] ? "uploads/mascotas/" . rawurlencode($m["url_foto"]) : "img/default-pet.svg"); ?>"
                                     alt="<?php echo htmlspecialchars($m["nombre"]); ?>"
                                     data-c3-error="evento35">
                            </div>
                            <div class="status-indicator">
                                <label class="toggle-switch" title="<?php echo htmlspecialchars((string)($m['estado'] == 1 ? 'Mascota Activa (Clic para desactivar)' : 'Mascota Inactiva (Clic para activar)'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="checkbox" <?php echo htmlspecialchars((string)($m['estado'] == 1 ? 'checked' : ''), ENT_QUOTES, 'UTF-8'); ?> data-c3-change="evento36" data-c3-arg0="<?php echo htmlspecialchars((string)($m['id_mascota']), ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        <div class="card-body-mini cursor-pointer" data-c3-click="evento37" data-c3-arg0="<?php echo (int) $m['id_mascota']; ?>">
                            <h3 class="card-title-mini"><?php echo htmlspecialchars($m["nombre"]); ?></h3>
                            <div class="card-tags-mini">
                                <?php if($m['estado'] == 0): ?>
                                    <span class="tag-mini"><i class="bi bi-moon-stars"></i> Inactivo</span>
                                <?php endif; ?>
                                <span class="tag-mini"><i class="fas fa-paw"></i> <?php echo htmlspecialchars($m["nombre_especie"]); ?> • <?php echo htmlspecialchars($m["nombre_raza"] ?? 'Sin Raza'); ?><?php if (!empty($m['raza_indicada'])): ?> (indicó «<?php echo htmlspecialchars($m['raza_indicada']); ?>»)<?php endif; ?></span>
                                <span class="tag-mini"><i class="fas fa-venus-mars"></i> <?php echo htmlspecialchars($m["sexo"]); ?></span>
                            </div>
                            <div class="card-contact-mini">
                                <span class="contact-text-mini"><i class="bi bi-person"></i> <?php echo htmlspecialchars($m["propietario_nombre"]); ?></span>
                                <span class="contact-text-mini"><i class="bi bi-file-medical"></i> HC: <?php echo htmlspecialchars($m["numero_historia_clinica"] ?: 'Sin asignar'); ?></span>
                            </div>
                        </div>
                        <div class="card-footer-mini">
                            <button class="action-btn-mini" data-c3-click="evento38" data-c3-arg0="<?php echo htmlspecialchars((string)($m['id_mascota']), ENT_QUOTES, 'UTF-8'); ?>" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            <?php if (!empty($m['numero_historia_clinica'])): ?>
                            <button class="action-btn-mini" data-c3-click="evento39" data-c3-arg0="<?php echo (int) $m['id_mascota']; ?>" data-c3-arg1="<?php echo htmlspecialchars(json_encode($m['nombre']), ENT_QUOTES); ?>" title="Ver Historial Clínico">
                                <i class="bi bi-file-earmark-medical-fill"></i>
                            </button>
                            <?php endif; ?>
                            <button class="action-btn-mini" data-c3-click="evento40" data-c3-arg0="<?php echo (int) $m['id_mascota']; ?>" title="Ver Ficha Básica">
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
                <form id="formPropietario" data-c3-submit="evento41">
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
                                    <input type="tel" name="telefono" id="new_owner_tel" required placeholder="Ej: 3001234567" minlength="<?= ValidadorTelefono::MIN ?>" <?= ValidadorTelefono::atributosHtml() ?> class="validate-tel">
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
                        </div>
                    </div>
                    <div class="users-modal__footer">
                        <button type="button" class="btn-modal-secondary close-modal-btn" data-modal="modalNuevoRegistro">Cancelar</button>
                        <div class="consentimiento-presencial">
                            <p>Entrega este formulario al titular para que lea la política y acepte directamente.</p>
                            <a href="index.php?action=privacidad" target="_blank" rel="noopener">Leer política de tratamiento de datos</a>
                            <label><input type="checkbox" name="titular_presente" value="1" required> Soy el titular y estoy presente.</label>
                            <label><input type="checkbox" name="acepta_politica" value="1" required> Acepto la política de tratamiento de datos.</label>
                        </div>
                        <button type="submit" class="btn-modal-primary">
                            <i class="fas fa-check-circle"></i> Registrar Propietario
                        </button>
                    </div>
                </form>
            </div>

            <!-- TAB MASCOTA -->
            <div id="tabNuevaMascota" class="modal-tab-content">
                <form id="formMascota" data-c3-submit="evento42" enctype="multipart/form-data">
                    <div class="users-modal__body">
                        <div class="form-grid-split">
                            <div class="photo-side">
                                <div class="preview-box">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Subir Foto</span>
                                    <img id="newPreview" src="" class="d-none">
                                    <button type="button" class="btn-clear-img clear-preview-btn d-none" id="btnClearNewImg" data-input="newFoto" data-preview="newPreview" title="Quitar imagen"><i class="fas fa-times"></i></button>
                                </div>
                                <input type="file" name="foto" id="newFoto" accept="image/jpeg,image/png" class="d-none image-upload-input" data-preview="newPreview" data-clear-btn="btnClearNewImg">

                                <div class="pet-extra-panel mt-1rem">
                                    <div class="input-group">
                                        <label class="m-0"><i class="fas fa-palette"></i> Colores Base</label>
                                        <select id="newSelectedColoresInput" name="colores[]" multiple required class="pacientes-estilo-4">
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
                                            <span id="text_pet_estado" class="pacientes-estilo-5 text-success">Activo</span>
                                        </div>
                                        <input type="hidden" name="estado" id="new_estado" value="1"><label for="new_esterilizado">Esterilización</label>
        <select name="esterilizado" id="new_esterilizado"><option value="">No se sabe</option><option value="1">Sí</option><option value="0">No</option></select>
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
                                                <input type="text" name="nombre" id="new_nombre" required placeholder="Ej: Firulais" maxlength="<?= ValidadorMascota::NOMBRE_MAX ?>">
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
                                                <select name="especie" id="new_especie" required data-c3-change="evento43">
                                                    <option value="">Seleccione...</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="input-group">
                                            <label>Raza</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-dna field-icon"></i>
                                                <select name="raza" id="new_raza" required data-c3-change="evento44">
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
                                                <input type="text" class="flatpickr-date" name="fecha_nacimiento" id="new_fecha_nacimiento" required placeholder="Seleccione fecha...">
                                            </div>
                                        </div>

                                        <div class="input-group">
                                            <label>Peso (Kg)</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-weight field-icon"></i>
                                                <input type="number" step="0.01" name="peso" id="new_peso" required placeholder="Ej: 4.5" min="0.1" max="<?= ValidadorMascota::PESO_MAX_KG ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="input-group d-none" id="newOtherBreedGroup">
                                        <label>Raza por confirmar</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-tag field-icon"></i>
                                            <input type="text" name="raza_indicada" maxlength="<?= ValidadorMascota::RAZA_MAX ?>" id="new_nueva_raza" placeholder="¿Qué raza es?">
                                        </div>
                                    </div>



                                    <div class="input-group rel-pos">
                                        <label>Vincular Propietario *</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-search field-icon"></i>
                                            <input type="text" id="ownerSearchInput" placeholder="Documento o correo completo..."  autocomplete="off">
                                            <button type="button" id="buscarPropietarioExacto" class="btn-modal-secondary">Buscar coincidencia exacta</button>
                                        </div>
                                        <input type="hidden" name="id_propietario" id="petOwnerDoc" required>
                                        <div id="ownerSuggestions" class="suggestions-list"></div>
                                        <div id="mascotasExistentes" class="mascotas-existentes" aria-live="polite"></div>
                                        <div id="selectedOwnerInfo" class="selected-badge d-none">
                                            <i class="fas fa-user-check"></i> <span id="selectedOwnerName"></span>
                                            <i class="fas fa-times-circle close-selected-owner" data-c3-click="evento45"></i>
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
                        <input type="file" name="foto" id="editFoto" accept="image/jpeg,image/png" class="d-none image-upload-input" data-preview="editPreview" data-clear-btn="btnClearEditImg">

                        <div class="pet-extra-panel mt-1rem">
                            <div class="input-group">
                                <label class="m-0"><i class="fas fa-palette"></i> Colores Base</label>
                                <select id="editSelectedColoresInput" name="colores[]" multiple required class="w-100 mt-2">
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
                                    <span id="text_edit_pet_estado" class="pacientes-estilo-6 text-success">Activo</span>
                                </div>
                                <input type="hidden" name="estado" id="edit_estado" value="1"><label for="edit_esterilizado">Esterilización</label>
        <select name="esterilizado" id="edit_esterilizado"><option value="">No se sabe</option><option value="1">Sí</option><option value="0">No</option></select>
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
                                        <input type="text" name="nombre" id="edit_nombre" required maxlength="<?= ValidadorMascota::NOMBRE_MAX ?>" class="validate-name">
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
                                        <input type="text" class="flatpickr-date" name="fecha_nacimiento" id="edit_fecha_nac" required placeholder="Seleccione fecha...">
                                    </div>
                                </div>

                                <div class="input-group">
                                    <label>Peso (Kg)</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-weight field-icon"></i>
                                        <input type="number" step="0.01" name="peso" id="edit_peso" required placeholder="0.00" min="0.01" max="<?= ValidadorMascota::PESO_MAX_KG ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- OTRA RAZA -->
                            <div class="form-grid-1-gap d-none" id="editOtherBreedGroup">
                                <div class="input-group">
                                    <label>Raza por confirmar <span class="pacientes-estilo-7">*</span></label>
                                    <div class="input-wrapper no-icon">
                                        <input type="text" name="raza_indicada" maxlength="<?= ValidadorMascota::RAZA_MAX ?>" id="edit_nueva_raza" placeholder="¿Qué raza es?">
                                    </div>
                                </div>
                            </div>

                            <!-- CAMBIAR PROPIETARIO -->
                            <div class="input-group rel-pos">
                                <label>Propietario de la mascota</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-user-edit field-icon"></i>
                                    <input type="text" id="editOwnerSearchInput" readonly aria-label="Propietario de la mascota">
                                </div>
                                <input type="hidden" name="id_propietario" id="edit_petOwnerDoc">
                                <div id="editOwnerSuggestions" class="suggestions-list"></div>
                                <div id="selectedEditOwnerInfo" class="selected-badge d-none">
                                    <div class="flex-center-gap">
                                        <i class="fas fa-user-check"></i> <span id="selectedEditOwnerName"></span>
                                    </div>

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
<div id="drawerHistorialOverlay" class="drawer-overlay" data-c3-click="evento46"></div>
<aside id="drawerHistorial" class="drawer">
    <div class="drawer-header">
        <button type="button" class="drawer-close" data-c3-click="evento47" aria-label="Cerrar">
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
            <input type="hidden" name="id_usuario" id="edit_owner_doc_orig">
            <div class="users-modal__body">
                <div class="form-grid">
                    <div class="input-group">
                        <label>Tipo Documento</label>
                        <div class="input-wrapper">
                            <i class="far fa-id-card field-icon"></i>
                            <select name="tipo_documento" id="edit_owner_tipo_doc" disabled required class="validate-select">
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
                            <input type="tel" name="telefono" id="edit_owner_tel" required placeholder="Ej: 3001234567" minlength="<?= ValidadorTelefono::MIN ?>" <?= ValidadorTelefono::atributosHtml() ?> class="validate-tel">
                        </div>
                        <span class="error-message display-none-error"></span>
                    </div>
                    <div class="input-group">
                        <label>Email</label>
                        <div class="input-wrapper">
                            <i class="far fa-envelope field-icon"></i>
                            <input type="email" name="email" id="edit_owner_email" readonly required placeholder="correo@ejemplo.com" maxlength="100" class="validate-email">
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
                            <span id="text_edit_owner_estado" class="pacientes-estilo-8 text-success">Activo en el sistema</span>
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
<div id="drawerVacunaOverlay" class="drawer-overlay" data-c3-click="evento48"></div>
<aside id="drawerVacuna" class="drawer">
    <div class="drawer-header">
        <button type="button" class="drawer-close" data-c3-click="evento49" aria-label="Cerrar">
            <i class="fas fa-times"></i>
        </button>
        <div class="drawer-title-wrap">
            <h2><i class="fas fa-syringe"></i> Registrar Vacunación</h2>
            <p id="vacunaPetName"></p>
        </div>
    </div>
    <form id="formVacuna" data-c3-submit="evento50">
        <input type="hidden" name="id_mascota" id="vacuna_id_mascota">
        <div class="drawer-body">
            <!-- Sección: Información de la Vacuna -->
            <div class="pacientes-estilo-9">
                <h4 class="pacientes-estilo-10">Información de la Vacuna</h4>
                <div class="form-grid">
                    <div class="input-group full">
                        <label>Nombre de la Vacuna *</label>
                        <select name="nombre_vacuna" required id="vacunaSelect" data-c3-change="evento51">
                            <option value="">Cargando vacunas...</option>
                        </select>
                    </div>
                    <div class="pacientes-estilo-11 input-group full" id="nuevaVacunaContainer">
                        <label>Nueva Vacuna (si no está en la lista) *</label>
                        <input type="text" name="nueva_vacuna" id="nuevaVacunaInput" placeholder="Escribe el nombre de la nueva vacuna">
                        <small class="pacientes-estilo-12">Esta vacuna se agregará al catálogo para futuros usos</small>
                    </div>
                    <div class="input-group">
                        <label>Laboratorio *</label>
                        <select name="laboratorio" required id="laboratorioSelect" data-c3-change="evento52">
                            <option value="">Seleccione laboratorio...</option>
                        </select>
                    </div>
                    <div class="pacientes-estilo-13 input-group" id="nuevoLaboratorioContainer">
                        <label>Nuevo Laboratorio (si no está en la lista) *</label>
                        <input type="text" name="nuevo_laboratorio" id="nuevoLaboratorioInput" placeholder="Escribe el nombre del nuevo laboratorio">
                        <small class="pacientes-estilo-14">Este laboratorio se agregará al catálogo para futuros usos</small>
                    </div>
                    <div class="input-group">
                        <label>Lote *</label>
                        <input type="text" name="lote" required placeholder="Ej. ABC123456" pattern="[A-Za-z0-9]+" title="Solo letras y números">
                    </div>
                </div>
            </div>

            <!-- Sección: Fechas -->
            <div class="pacientes-estilo-15">
                <h4 class="pacientes-estilo-16">Fechas</h4>
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
                <h4 class="pacientes-estilo-17">Observaciones</h4>
                <div class="input-group full">
                    <textarea name="observaciones" rows="2" placeholder="Notas adicionales sobre la vacunación..."></textarea>
                </div>
            </div>

            <button type="submit" class="pacientes-estilo-18 btn-primary full-btn">Guardar Registro</button>
        </div>
    </form>
</aside>

<!-- DRAWER REGISTRAR DESPARASITACIÓN -->
<div id="drawerDesparasitacionOverlay" class="drawer-overlay" data-c3-click="evento53"></div>
<aside id="drawerDesparasitacion" class="drawer">
    <div class="drawer-header">
        <button type="button" class="drawer-close" data-c3-click="evento54" aria-label="Cerrar">
            <i class="fas fa-times"></i>
        </button>
        <div class="drawer-title-wrap">
            <h2><i class="fas fa-bug"></i> Registrar Desparasitación</h2>
            <p id="despPetName"></p>
        </div>
    </div>
    <form id="formDesparasitacion" data-c3-submit="evento55">
        <input type="hidden" name="id_mascota" id="desp_id_mascota">
        <div class="drawer-body">
            <!-- Sección: Tipo y Producto -->
            <div class="pacientes-estilo-19">
                <h4 class="pacientes-estilo-20">Tipo y Producto</h4>
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
                        <select name="producto" required id="productoSelect" data-c3-change="evento56">
                            <option value="">Seleccione producto...</option>
                        </select>
                    </div>
                    <div class="pacientes-estilo-21 input-group full" id="nuevoProductoContainer">
                        <label>Nuevo Producto (si no está en la lista) *</label>
                        <input type="text" name="nuevo_producto" id="nuevoProductoInput" placeholder="Escribe el nombre del nuevo producto">
                        <small class="pacientes-estilo-22">Este producto se agregará al catálogo para futuros usos</small>
                    </div>
                </div>
            </div>

            <!-- Sección: Periodicidad y Fecha -->
            <div class="pacientes-estilo-23">
                <h4 class="pacientes-estilo-24">Periodicidad y Fecha</h4>
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
                <div class="pacientes-estilo-25">
                    <i class="fas fa-info-circle"></i> La fecha de la próxima dosis se calculará automáticamente según la periodicidad.
                </div>
            </div>

            <!-- Sección: Observaciones -->
            <div>
                <h4 class="pacientes-estilo-26">Observaciones</h4>
                <div class="input-group full">
                    <textarea name="observaciones" rows="2" placeholder="Notas adicionales sobre la desparasitación..."></textarea>
                </div>
            </div>

            <button type="submit" class="pacientes-estilo-27 btn-primary full-btn">Guardar Registro</button>
        </div>
    </form>
</aside>

<!-- MODAL AGENDAR CITA -->
<div id="modalCita" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="far fa-calendar-check"></i> Agendar Cita</h3>
            <span class="close" data-c3-click="evento57">&times;</span>
        </div>
        <form id="formCita" data-c3-submit="evento58">
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
                        <select name="id_veterinario" id="cita_veterinario" required>
                            <option value="">Cargando veterinarios...</option>
                        </select>
                    </div>
                    <div class="input-group full">
                        <label>Motivo de la Cita *</label>
                        <input type="text" name="motivo" required placeholder="Ej. Chequeo general, Vacunación, Enfermedad...">
                    </div>
                </div>
                <div class="pacientes-estilo-28">
                    <i class="fas fa-envelope"></i> Se enviará un correo de confirmación al propietario.
                </div>
                <button type="submit" class="pacientes-estilo-29 btn-primary full-btn">Confirmar Cita</button>
            </div>
        </form>
    </div>
</div>

<div id="lightboxVisor" class="lightbox" data-c3-click="evento59">
    <img src="" alt="Vista previa">
</div>

<!-- intl-tel-input JS -->
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.10/build/js/intlTelInput.min.js"></script>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>

