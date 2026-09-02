<?php
require_once '../config/Database.php';
require_once '../models/Usuario.php';
require_once '../models/Mascota.php';

$database = new Database();
$db = $database->getConnection();
$usuarioModel = new Usuario($db);
$mascotaModel = new Mascota($db);

// Obtener personal (roles 1, 2, 3 - admin, vet, reception)
$personal = $usuarioModel->getAll();

// Obtener propietarios (rol 4)
$propietarios = $usuarioModel->getAllOwners();

// Para cada propietario, obtener sus mascotas
foreach ($propietarios as &$propietario) {
    $mascotas = $mascotaModel->getByPropietario($propietario['documento']);
    $propietario['mascotas'] = $mascotas;
    $propietario['num_mascotas'] = count($mascotas);
}
unset($propietario);

?>

<!-- intl-tel-input CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.10/build/css/intlTelInput.css">

<div class="users-container">
    <div class="header-container-white">
        <div class="head-title-desc">
            <h1 class="users-page-title">Gestión de Usuarios</h1>
            <p class="users-module-desc">
                Administra las cuentas del personal de la clínica y gestiona a los clientes y sus mascotas.
            </p>
        </div>
        <!-- Top Right: Tabs and Add User -->
        <div class="head-top-right">
            <div class="tabs-wrapper">
                <button class="tab-btn active" data-tab="personal">
                    <i class="fas fa-users-cog"></i>
                    <span>Personal</span>
                </button>
                <button class="tab-btn" data-tab="clientes">
                    <i class="fas fa-user-friends"></i>
                    <span>Clientes</span>
                </button>
            </div>
            <button class="btn-create btn-create-large" onclick="abrirModalUsuarioNuevo()">
                <i class="fas fa-plus"></i>
                <span>Nuevo Usuario</span>
            </button>
        </div>

        <!-- Filtros Personal -->
        <div class="users-controls-bar__filters is-active" id="usersFiltersPersonal">
            <div class="head-search">
                <div class="search-input">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchPersonal" placeholder="Buscar por nombre o documento...">
                </div>
            </div>
            <div class="head-status-filters">
                <select class="filter-select" id="filterRol">
                    <option value="">Todos los roles</option>
                    <option value="1">Administrador</option>
                    <option value="2">Veterinario</option>
                    <option value="3">Recepcionista</option>
                </select>
                <div class="segmented-control" id="filterEstado">
                    <input type="radio" name="estado_personal" id="ep_todos" value="" checked>
                    <label for="ep_todos">Todos</label>
                    <input type="radio" name="estado_personal" id="ep_activos" value="1">
                    <label for="ep_activos">Activos</label>
                    <input type="radio" name="estado_personal" id="ep_inactivos" value="0">
                    <label for="ep_inactivos">Inactivos</label>
                </div>
            </div>
        </div>

        <!-- Filtros Clientes -->
        <div class="users-controls-bar__filters" id="usersFiltersClientes">
            <div class="head-search">
                <div class="search-input">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchClientes" placeholder="Buscar cliente por nombre o documento...">
                </div>
            </div>
            <div class="head-status-filters">
                <div class="segmented-control" id="filterEstadoClientes">
                    <input type="radio" name="estado_clientes" id="ec_todos" value="" checked>
                    <label for="ec_todos">Todos</label>
                    <input type="radio" name="estado_clientes" id="ec_activos" value="1">
                    <label for="ec_activos">Activos</label>
                    <input type="radio" name="estado_clientes" id="ec_inactivos" value="0">
                    <label for="ec_inactivos">Inactivos</label>
                </div>
            </div>
        </div>

        <!-- View Toggle -->
        <div class="head-view-toggle">
            <div class="view-toggle" role="group" aria-label="Tipo de vista">
                <button type="button" class="view-toggle-btn" onclick="switchView('table')" id="viewTableBtn" title="Vista tabla">
                    <i class="bi-table"></i>
                </button>
                <button type="button" class="view-toggle-btn active" onclick="switchView('cards')" id="viewCardsBtn" title="Vista cards">
                    <i class="bi-grid"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Tab: Personal -->
    <div class="tab-panel active" id="tab-personal">
        <div class="personal-grid view-grid" id="personalGrid">
            <?php if (empty($personal)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-users-slash"></i>
                    </div>
                    <h3>No hay personal registrado</h3>
                    <p>Crea el primer usuario para comenzar</p>
                </div>
            <?php else: ?>
                <?php foreach ($personal as $user): ?>
                    <?php $isCurrentUser = isset($_SESSION['usuario_doc']) && $_SESSION['usuario_doc'] == $user['documento']; ?>
                    <div class="person-card <?= $isCurrentUser ? 'current-user' : '' ?>" data-search="<?= strtolower($user['nombre_completo'] . ' ' . $user['documento']) ?>" data-rol="<?= $user['id_rol'] ?>" data-estado="<?= $user['estado'] ?>" data-current="<?= $isCurrentUser ? 'true' : 'false' ?>">
                        <div class="card-header-mini">
                            <div class="avatar-mini">
                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['nombre_completo']) ?>&background=<?= $user['id_rol'] == 1 ? '0052FF' : ($user['id_rol'] == 2 ? '0044d6' : '5560FF') ?>&color=fff&size=128" alt="<?= htmlspecialchars($user['nombre_completo']) ?>">
                            </div>
                            <div class="status-indicator">
                                <label class="toggle-switch <?= $isCurrentUser ? 'disabled' : '' ?>" title="<?= $user['estado'] == 1 ? 'Usuario Activo (Clic para desactivar)' : 'Usuario Inactivo (Clic para activar)' ?>">
                                    <input type="checkbox" <?= $user['estado'] == 1 ? 'checked' : '' ?> <?= $isCurrentUser ? 'disabled' : '' ?> onchange="toggleUserStatus('<?= $user['documento'] ?>', this.checked ? 1 : 0, <?= $isCurrentUser ? 'true' : 'false' ?>)">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        <div class="card-body-mini">
                            <h3 class="card-title-mini">
                                <?= htmlspecialchars($user['nombre_completo']) ?>
                                <?php if ($isCurrentUser): ?><span class="current-user-label">(Tú)</span><?php endif; ?>
                            </h3>
                            <div class="card-tags-mini">
                                <?php if($user['estado'] == 0): ?>
                                <span class="tag-mini"><i class="bi bi-moon-stars"></i> Inactivo</span>
                                <?php endif; ?>
                                <span class="tag-mini"><i class="bi <?= $user['id_rol'] == 1 ? 'bi-shield-shaded' : ($user['id_rol'] == 2 ? 'bi-heart-pulse' : 'bi-headset') ?>"></i> <?= htmlspecialchars($user['nombre_rol']) ?></span>
                            </div>
                            <div class="card-contact-mini">
                                <span class="contact-text-mini"><i class="bi bi-person-badge"></i> <?= htmlspecialchars($user['documento']) ?></span>
                                <?php if(!empty($user['telefono'])): ?>
                                <span class="contact-text-mini"><i class="bi bi-telephone"></i> <?= htmlspecialchars($user['telefono']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer-mini">
                            <button class="action-btn-mini" onclick="editUser('<?= $user['documento'] ?>')" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            <button class="action-btn-mini" onclick="resetPassword('<?= $user['documento'] ?>')" title="Restablecer Contraseña">
                                <i class="bi bi-key"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Vista Tabla Personal -->
        <div class="table-view" id="personalTable" style="display: none;">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Documento</th>
                        <th>Rol</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($personal)): ?>
                        <tr>
                            <td colspan="7" class="empty-table">
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="fas fa-users-slash"></i>
                                    </div>
                                    <h3>No hay personal registrado</h3>
                                    <p>Crea el primer usuario para comenzar</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($personal as $user): ?>
                            <?php $isCurrentUser = isset($_SESSION['usuario_doc']) && $_SESSION['usuario_doc'] == $user['documento']; ?>
                            <tr data-search="<?= strtolower($user['nombre_completo'] . ' ' . $user['documento']) ?>" data-rol="<?= $user['id_rol'] ?>" data-estado="<?= $user['estado'] ?>">
                                <td>
                                    <div class="table-user">
                                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['nombre_completo']) ?>&background=<?= $user['id_rol'] == 1 ? '0052FF' : ($user['id_rol'] == 2 ? '0044d6' : '5560FF') ?>&color=fff&size=64" alt="<?= htmlspecialchars($user['nombre_completo']) ?>">
                                        <div>
                                            <strong><?= htmlspecialchars($user['nombre_completo']) ?></strong>
                                            <?php if ($isCurrentUser): ?>
                                            <span class="current-user-label">(Tú)</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($user['documento']) ?></td>
                                <td><span class="role-badge role-<?= $user['id_rol'] ?>"><?= htmlspecialchars($user['nombre_rol']) ?></span></td>
                                <td><?= htmlspecialchars($user['email'] ?? 'No registrado') ?></td>
                                <td><?= htmlspecialchars($user['telefono'] ?? 'No registrado') ?></td>
                                <td>
                                    <span class="status-badge status-<?= $user['estado'] == 1 ? 'active' : 'inactive' ?>">
                                        <?= $user['estado'] == 1 ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <label class="toggle-switch toggle-sm <?= $isCurrentUser ? 'disabled' : '' ?>">
                                            <input type="checkbox" <?= $user['estado'] == 1 ? 'checked' : '' ?> <?= $isCurrentUser ? 'disabled' : '' ?> onchange="toggleUserStatus('<?= $user['documento'] ?>', this.checked ? 1 : 0, <?= $isCurrentUser ? 'true' : 'false' ?>)">
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <button class="action-btn action-edit" onclick="editUser('<?= $user['documento'] ?>')" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn action-password" onclick="resetPassword('<?= $user['documento'] ?>')" title="Restablecer Contraseña">
                                            <i class="fas fa-key"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab: Clientes -->
    <div class="tab-panel" id="tab-clientes">
        <div class="clients-grid view-grid" id="clientesGrid">
            <?php if (empty($propietarios)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-users-slash"></i>
                    </div>
                    <h3>No hay clientes registrados</h3>
                    <p>Los clientes aparecerán aquí cuando se registren</p>
                </div>
            <?php else: ?>
                <?php foreach ($propietarios as $propietario): ?>
                    <div class="client-card person-card" data-search="<?= strtolower($propietario['nombre_completo'] . ' ' . $propietario['documento']) ?>" data-estado="<?= $propietario['estado'] ?>">
                        <div class="card-header-mini">
                            <div class="avatar-mini cursor-pointer" onclick="verDetalleCliente('<?= $propietario['documento'] ?>')">
                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($propietario['nombre_completo']) ?>&background=5560FF&color=fff&size=128" alt="<?= htmlspecialchars($propietario['nombre_completo']) ?>">
                            </div>
                            <div class="status-indicator">
                                <label class="toggle-switch" onclick="event.stopPropagation()" title="<?= $propietario['estado'] == 1 ? 'Cliente Activo (Clic para desactivar)' : 'Cliente Inactivo (Clic para activar)' ?>">
                                    <input type="checkbox" <?= $propietario['estado'] == 1 ? 'checked' : '' ?> onchange="toggleUserStatus('<?= $propietario['documento'] ?>', this.checked ? 1 : 0)">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        <div class="card-body-mini cursor-pointer" onclick="verDetalleCliente('<?= $propietario['documento'] ?>')">
                            <h3 class="card-title-mini"><?= htmlspecialchars($propietario['nombre_completo']) ?></h3>
                            <div class="card-tags-mini">
                                <?php if($propietario['estado'] == 0): ?>
                                <span class="tag-mini"><i class="bi bi-moon-stars"></i> Inactivo</span>
                                <?php endif; ?>
                                <span class="tag-mini"><i class="bi bi-phone"></i> <?= htmlspecialchars($propietario['telefono'] ?? 'N/A') ?></span>
                            </div>
                            <div class="card-contact-mini">
                                <span class="contact-text-mini"><i class="bi bi-person-badge"></i> <?= htmlspecialchars($propietario['documento']) ?></span>
                            </div>
                        </div>
                        <div class="card-footer-mini">
                            <button class="action-btn-mini" onclick="editUser('<?= $propietario['documento'] ?>')" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            <button class="action-btn-mini" onclick="verDetalleCliente('<?= $propietario['documento'] ?>')" title="Ver detalles de mascotas">
                                <i class="bi bi-eye-fill"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Vista Tabla Clientes -->
        <div class="table-view" id="clientesTable" style="display: none;">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Documento</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Mascotas</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($propietarios)): ?>
                        <tr>
                            <td colspan="7" class="empty-table">
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="fas fa-users-slash"></i>
                                    </div>
                                    <h3>No hay clientes registrados</h3>
                                    <p>Los clientes aparecerán aquí cuando se registren</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($propietarios as $propietario): ?>
                            <tr data-search="<?= strtolower($propietario['nombre_completo'] . ' ' . $propietario['documento']) ?>" data-estado="<?= $propietario['estado'] ?>">
                                <td>
                                    <div class="table-user">
                                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($propietario['nombre_completo']) ?>&background=5560FF&color=fff&size=64" alt="<?= htmlspecialchars($propietario['nombre_completo']) ?>">
                                        <div>
                                            <strong><?= htmlspecialchars($propietario['nombre_completo']) ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($propietario['documento']) ?></td>
                                <td><?= htmlspecialchars($propietario['email'] ?? 'No registrado') ?></td>
                                <td><?= htmlspecialchars($propietario['telefono'] ?? 'No registrado') ?></td>
                                <td><?= $propietario['num_mascotas'] ?> mascota(s)</td>
                                <td>
                                    <span class="status-badge status-<?= $propietario['estado'] == 1 ? 'active' : 'inactive' ?>">
                                        <?= $propietario['estado'] == 1 ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <label class="toggle-switch toggle-sm">
                                            <input type="checkbox" <?= $propietario['estado'] == 1 ? 'checked' : '' ?> onchange="toggleUserStatus('<?= $propietario['documento'] ?>', this.checked ? 1 : 0)">
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <button class="action-btn action-view btn-action-view" onclick="verDetalleCliente('<?= $propietario['documento'] ?>')" title="Ver detalles de mascotas">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn action-edit" onclick="editUser('<?= $propietario['documento'] ?>')" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn action-password" onclick="resetPassword('<?= $propietario['documento'] ?>')" title="Restablecer Contraseña">
                                            <i class="fas fa-key"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- intl-tel-input JS -->
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.10/build/js/intlTelInput.min.js"></script>

<script>
let itiPhone;
const CURRENT_USER_DOC = '<?= $_SESSION['usuario_doc'] ?? '' ?>';

// Tabs functionality
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const tab = this.dataset.tab;

        // Remove active class from all tabs and buttons
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));

        // Add active class to clicked button and corresponding panel
        this.classList.add('active');
        document.getElementById('tab-' + tab).classList.add('active');

        document.getElementById('usersFiltersPersonal').classList.toggle('is-active', tab === 'personal');
        document.getElementById('usersFiltersClientes').classList.toggle('is-active', tab === 'clientes');

        // Trigger stagger animation for cards in the active tab
        const activePanel = document.getElementById('tab-' + tab);
        const cards = activePanel.querySelectorAll('.person-card, .client-card');
        cards.forEach((card, index) => {
            card.style.animation = 'none';
            card.offsetHeight; // Trigger reflow
            card.style.animation = `cardEntrance 0.5s ease forwards ${index * 0.08}s`;
        });
    });
});

// Initial stagger animation on page load
document.addEventListener('DOMContentLoaded', function() {
    const allCards = document.querySelectorAll('.person-card, .client-card');
    allCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.08}s`;
    });
    
    const telInput = document.querySelector("#user_tel");
    if (telInput) {
        itiPhone = window.intlTelInput(telInput, {
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

function switchView(view) {
    const activeTab = document.querySelector('.tab-panel.active');
    const tabId = activeTab.id;
    
    // Update toggle buttons
    document.getElementById('viewCardsBtn').classList.remove('active');
    document.getElementById('viewTableBtn').classList.remove('active');
    
    const personalGrid = document.getElementById('personalGrid');
    const clientesGrid = document.getElementById('clientesGrid');
    const personalTable = document.getElementById('personalTable');
    const clientesTable = document.getElementById('clientesTable');

    if (view === 'cards') {
        document.getElementById('viewCardsBtn').classList.add('active');
        if (tabId === 'tab-personal') {
            personalGrid.style.display = '';
            personalTable.style.display = 'none';
        } else {
            clientesGrid.style.display = '';
            clientesTable.style.display = 'none';
        }
    } else {
        document.getElementById('viewTableBtn').classList.add('active');
        if (tabId === 'tab-personal') {
            personalGrid.style.display = 'none';
            personalTable.style.display = 'block';
        } else {
            clientesGrid.style.display = 'none';
            clientesTable.style.display = 'block';
        }
    }
}

async function editUser(doc) {
    try {
        if (!doc || doc === '' || doc === 'null' || doc === 'undefined') {
            Swal.fire('Error', 'El usuario no tiene un documento válido. No se puede editar.', 'error');
            return;
        }
        
        const res = await (await fetch(`index.php?action=get_usuario_ajax&documento=${encodeURIComponent(doc)}`)).json();
        
        if (!res || !res.documento) {
            console.error('Error al cargar usuario:', res);
            const errorMsg = res && res.error ? res.error : 'No se pudieron cargar los datos del usuario';
            Swal.fire('Error', `${errorMsg}. Documento: ${doc}`, 'error');
            return;
        }

        const modalTitle = document.getElementById('modalUsuarioTitle');
        if (modalTitle) {
            modalTitle.innerHTML = `<i class="fas fa-user-edit"></i> Editar Usuario<span class="modal-subtitle"><i class="bi bi-person"></i> ${res.nombre_completo || ''} &nbsp;&bull;&nbsp; <i class="bi bi-card-text"></i> ${doc}</span>`;
        }
        
        const groupTipoDoc = document.getElementById('group_tipo_doc');
        const groupDoc = document.getElementById('group_doc');
        if (groupTipoDoc) groupTipoDoc.style.display = 'none';
        if (groupDoc) groupDoc.style.display = 'none';
        
        document.getElementById('user_doc').value = res.documento;
        document.getElementById('user_doc').disabled = true;
        document.getElementById('user_original_doc').value = res.documento;
        document.getElementById('user_tipo_doc').value = res.tipo_documento || 'CC';
        document.getElementById('user_nombre').value = res.nombre_completo || '';
        document.getElementById('user_email').value = res.email || '';
        document.getElementById('user_tel').value = res.telefono || '';
        if (typeof itiPhone !== 'undefined') {
            if (res.telefono) {
                itiPhone.setNumber(res.telefono);
            } else {
                itiPhone.setNumber('');
            }
        }
        const groupRol = document.getElementById('group_rol');
        const selectRol = document.getElementById('user_rol');
        const activeTab = document.querySelector('.tab-btn.active').dataset.tab;
        
        if (res.id_rol == 4 || activeTab === 'clientes') {
            // Es un cliente, no puede cambiar de rol
            if (groupRol) groupRol.style.display = 'none';
            selectRol.value = '4';
            // Ajustar layout a mitis mitis
            const nombreGroup = document.getElementById('user_nombre').closest('.input-group');
            if (nombreGroup) nombreGroup.style.gridColumn = 'span 1';
        } else {
            // Es personal, mostrar roles pero esconder opción de cliente
            if (groupRol) groupRol.style.display = '';
            for (let i = 0; i < selectRol.options.length; i++) {
                if (selectRol.options[i].value === '4' || selectRol.options[i].value === '3') {
                    selectRol.options[i].hidden = true;
                    selectRol.options[i].disabled = true;
                } else {
                    selectRol.options[i].hidden = false;
                    selectRol.options[i].disabled = false;
                }
            }
            selectRol.value = res.id_rol || '';
            
            // Si el usuario actual está editando su propio perfil, bloqueamos el rol
            if (doc === CURRENT_USER_DOC) {
                selectRol.disabled = true;
                if (groupRol) groupRol.title = 'No puedes cambiar tu propio rol';
            } else {
                selectRol.disabled = false;
                if (groupRol) groupRol.title = '';
            }
            // Restaurar layout
            const nombreGroup = document.getElementById('user_nombre').closest('.input-group');
            if (nombreGroup) nombreGroup.style.gridColumn = 'span 2';
        }
        
        const estadoValue = res.estado ?? '1';
        document.getElementById('user_estado_hidden').value = estadoValue;
        
        const toggleEstado = document.getElementById('user_estado_toggle');
        const textEstado = document.getElementById('user_estado_text');
        if (toggleEstado && textEstado) {
            toggleEstado.checked = (estadoValue == '1');
            textEstado.textContent = toggleEstado.checked ? 'Activo' : 'Inactivo';
            textEstado.style.color = toggleEstado.checked ? '#10B981' : '#EF4444';
            
            // Si el usuario actual está editando su propio perfil, bloqueamos el toggle
            const toggleContainer = toggleEstado.closest('label.toggle-switch');
            if (doc === CURRENT_USER_DOC) {
                toggleEstado.disabled = true;
                if (toggleContainer) {
                    toggleContainer.style.opacity = '0.5';
                    toggleContainer.title = 'No puedes desactivar tu propio usuario';
                    toggleContainer.style.cursor = 'not-allowed';
                }
            } else {
                toggleEstado.disabled = false;
                if (toggleContainer) {
                    toggleContainer.style.opacity = '1';
                    toggleContainer.title = '';
                    toggleContainer.style.cursor = 'pointer';
                }
            }
        }
        
        document.getElementById('user_pass').value = '';
        
        const passGroup = document.getElementById('user_pass_group');
        if (passGroup) {
            passGroup.style.display = 'none';
        }
        
        document.getElementById('user_pass').required = false;
        
        const estadoGroup = document.getElementById('user_estado_group');
        if (estadoGroup) {
            estadoGroup.style.display = '';
        }
        
        const btnGuardar = document.getElementById('btnGuardarUsuario');
        if (btnGuardar) {
            btnGuardar.innerHTML = '<i class="fas fa-save"></i> Guardar Cambios';
        }
        
        limpiarErroresModalUsuario();
        document.getElementById(MODAL_USUARIO_ID).style.display = 'flex';
        document.body.style.overflow = 'hidden';
    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
    }
}

async function guardarUsuarioForm(event) {
    event.preventDefault();
    
    // Forzar validación de teléfono por si acaso
    const telInput = document.getElementById('user_tel');
    if (telInput) validarTelefono(telInput);
    
    // Check if any error messages are visible
    const hasErrors = Array.from(document.querySelectorAll('#formUsuarioGestion .error-message')).some(el => el.style.display === 'block');
    if (hasErrors) {
        Swal.fire('Atención', 'Por favor, corrija los campos marcados en rojo antes de guardar.', 'warning');
        return;
    }

    const isEdit = document.getElementById('user_original_doc').value !== '';
    const action = isEdit ? 'actualizar_usuario_ajax' : 'registrar_usuario_ajax';

    // Habilitar select disabled temporalmente para que FormData lo capture
    const selectRol = document.getElementById('user_rol');
    const wasDisabled = selectRol.disabled;
    if (wasDisabled) selectRol.disabled = false;
    
    const formData = new FormData(event.target);
    
    // Sobrescribir el teléfono con el formato completo (+57...)
    if (typeof itiPhone !== 'undefined' && itiPhone.isValidNumber()) {
        formData.set('telefono', itiPhone.getNumber());
    }
    
    // Volver a deshabilitar por si acaso
    if (wasDisabled) selectRol.disabled = true;

    try {
        const res = await (await fetch(`index.php?action=${action}`, {
            method: 'POST',
            body: formData
        })).json();

        if (res.success) {
            cerrarModalUsuario();
            Swal.fire({
                title: '¡Listo!',
                text: res.message,
                icon: 'success',
                confirmButtonColor: '#0052FF'
            }).then(() => location.reload());
        } else {
            Swal.fire('Error', res.message || 'No se pudo guardar', 'error');
        }
    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
    }
}

function togglePasswordUsuario() {
    const input = document.getElementById('user_pass');
    const icon = document.querySelector('#user_pass_group .toggle-password-btn i');
    if (!input || !icon) return;
    input.type = input.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
}

function toggleUserStatus(doc, newStatus, isCurrentUser = false) {
    if (isCurrentUser) {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2500,
            timerProgressBar: true
        });
        
        Toast.fire({
            icon: 'warning',
            title: 'No puedes desactivarte a ti mismo'
        });
        return;
    }
    
    const actionWord = newStatus === 1 ? 'activado' : 'desactivado';
    
    // Llamada AJAX para actualizar el estado
    fetch('index.php?action=cambiar_estado_usuario_ajax', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `documento=${encodeURIComponent(doc)}&estado=${newStatus}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
            
            Toast.fire({
                icon: 'success',
                title: `Usuario ${actionWord}`
            });
        } else {
            // Revertir el toggle si hubo error
            const toggle = document.querySelector(`input[onchange*="${doc}"]`);
            if (toggle) {
                toggle.checked = !toggle.checked;
            }
            
            Swal.fire('Error', data.message || 'No se pudo actualizar el estado', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Revertir el toggle si hubo error
        const toggle = document.querySelector(`input[onchange*="${doc}"]`);
        if (toggle) {
            toggle.checked = !toggle.checked;
        }
        
        Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
    });
}

function resetPassword(doc) {
    Swal.fire({
        title: 'Restablecer Contraseña',
        text: `¿Restablecer contraseña para el usuario ${doc}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#5560FF',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, restablecer',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Aquí iría la llamada AJAX para restablecer la contraseña
            Swal.fire('Éxito', 'Contraseña restablecida correctamente. La nueva contraseña es: 123456', 'success');
        }
    });
}

const MODAL_USUARIO_ID = 'modalUsuarioGestion';

function abrirModalUsuarioNuevo() {
    document.getElementById('formUsuarioGestion').reset();
    document.getElementById('user_original_doc').value = '';
    document.getElementById('user_doc').disabled = false;

    const activeTab = document.querySelector('.tab-btn.active').dataset.tab;
    const groupRol = document.getElementById('group_rol');
    const selectRol = document.getElementById('user_rol');
    
    if (activeTab === 'clientes') {
        document.getElementById('modalUsuarioTitle').innerHTML = '<i class="fas fa-user-plus"></i> Nuevo Cliente';
        if (groupRol) groupRol.style.display = 'none';
        selectRol.value = '4';
    } else {
        document.getElementById('modalUsuarioTitle').innerHTML = '<i class="fas fa-user-plus"></i> Nuevo Personal';
        if (groupRol) groupRol.style.display = '';
        selectRol.value = '';
        for (let i = 0; i < selectRol.options.length; i++) {
            if (selectRol.options[i].value === '4' || selectRol.options[i].value === '3') {
                selectRol.options[i].hidden = true;
                selectRol.options[i].disabled = true;
            } else {
                selectRol.options[i].hidden = false;
                selectRol.options[i].disabled = false;
            }
        }
    }
    
    const groupTipoDoc = document.getElementById('group_tipo_doc');
    const groupDoc = document.getElementById('group_doc');
    if (groupTipoDoc) groupTipoDoc.style.display = '';
    if (groupDoc) groupDoc.style.display = '';
    
    // Restaurar layout de nombre
    const nombreGroup = document.getElementById('user_nombre').closest('.input-group');
    if (nombreGroup) nombreGroup.style.gridColumn = 'span 2';
    
    const passGroup = document.getElementById('user_pass_group');
    if (passGroup) {
        passGroup.style.display = 'none';
    }
    
    document.getElementById('user_pass').required = false;
    
    document.getElementById('user_estado_hidden').value = '1';
    const toggleEstado = document.getElementById('user_estado_toggle');
    const textEstado = document.getElementById('user_estado_text');
    if (toggleEstado && textEstado) {
        toggleEstado.checked = true;
        textEstado.textContent = 'Activo';
        textEstado.style.color = '#10B981';
    }

    const estadoGroup = document.getElementById('user_estado_group');
    if (estadoGroup) {
        estadoGroup.style.display = '';
    }
    
    document.getElementById('btnGuardarUsuario').innerHTML =
        '<i class="fas fa-save"></i> Crear Usuario';
    limpiarErroresModalUsuario();
    if (typeof itiPhone !== 'undefined') {
        itiPhone.setNumber('');
    }
    document.getElementById(MODAL_USUARIO_ID).style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function cerrarModalUsuario() {
    document.getElementById(MODAL_USUARIO_ID).style.display = 'none';
    document.body.style.overflow = '';
}

function limpiarErroresModalUsuario() {
    document.querySelectorAll('#formUsuarioGestion .error-message').forEach(el => {
        el.style.display = 'none';
        el.textContent = '';
    });
    document.querySelectorAll('#formUsuarioGestion .input-group input, #formUsuarioGestion .input-group select')
        .forEach(el => el.classList.remove('error'));
}

// Búsqueda y filtrado de personal
function filterPersonal() {
    const searchTerm = document.getElementById('searchPersonal').value.toLowerCase();
    const rolFilter = document.getElementById('filterRol').value;
    const estadoFilter = document.querySelector('input[name="estado_personal"]:checked').value;
    
    // Filtrar cards
    const cards = document.querySelectorAll('.person-card');
    let visibleCards = 0;
    cards.forEach(card => {
        const searchData = card.getAttribute('data-search');
        const cardRol = card.getAttribute('data-rol');
        const cardEstado = card.getAttribute('data-estado');

        const matchesSearch = !searchTerm || (searchData && searchData.includes(searchTerm));
        const matchesRol = !rolFilter || cardRol === rolFilter;
        const matchesEstado = !estadoFilter || cardEstado === estadoFilter;

        if (matchesSearch && matchesRol && matchesEstado) {
            card.style.display = '';
            visibleCards++;
        } else {
            card.style.display = 'none';
        }
    });

    const personalGrid = document.getElementById('personalGrid');
    let personalGridEmpty = document.getElementById('personalGridEmptyMsg');
    if (visibleCards === 0 && cards.length > 0) {
        if (!personalGridEmpty) {
            personalGridEmpty = document.createElement('div');
            personalGridEmpty.id = 'personalGridEmptyMsg';
            personalGridEmpty.className = 'empty-state empty-state-filter';
            personalGridEmpty.innerHTML = '<div class="empty-icon"><i class="fas fa-search"></i></div><h3>Sin resultados</h3><p>No se encontraron empleados que coincidan con la búsqueda o filtros aplicados.</p>';
            personalGrid.appendChild(personalGridEmpty);
        }
        personalGridEmpty.style.display = 'block';
    } else if (personalGridEmpty) {
        personalGridEmpty.style.display = 'none';
    }

    // Filtrar tabla
    const tableRows = document.querySelectorAll('#personalTable tbody tr:not(#personalTableEmptyMsg)');
    let visibleRows = 0;
    tableRows.forEach(row => {
        const searchData = row.getAttribute('data-search');
        const cardRol = row.getAttribute('data-rol');
        const cardEstado = row.getAttribute('data-estado');

        const matchesSearch = !searchTerm || (searchData && searchData.includes(searchTerm));
        const matchesRol = !rolFilter || cardRol === rolFilter;
        const matchesEstado = !estadoFilter || cardEstado === estadoFilter;

        if (matchesSearch && matchesRol && matchesEstado) {
            row.style.display = '';
            visibleRows++;
        } else {
            row.style.display = 'none';
        }
    });

    const personalTableBody = document.querySelector('#personalTable tbody');
    let personalTableEmpty = document.getElementById('personalTableEmptyMsg');
    if (visibleRows === 0 && tableRows.length > 0) {
        if (!personalTableEmpty) {
            personalTableEmpty = document.createElement('tr');
            personalTableEmpty.id = 'personalTableEmptyMsg';
            personalTableEmpty.className = 'empty-table-row';
            personalTableEmpty.innerHTML = '<td colspan="7"><div class="empty-state empty-state-filter"><div class="empty-icon"><i class="fas fa-search"></i></div><h3>Sin resultados</h3><p>No se encontraron empleados que coincidan con la búsqueda o filtros aplicados.</p></div></td>';
            personalTableBody.appendChild(personalTableEmpty);
        }
        personalTableEmpty.style.display = '';
    } else if (personalTableEmpty) {
        personalTableEmpty.style.display = 'none';
    }
}

document.getElementById('searchPersonal').addEventListener('keyup', filterPersonal);
document.getElementById('filterRol').addEventListener('change', filterPersonal);
document.querySelectorAll('input[name="estado_personal"]').forEach(radio => radio.addEventListener('change', filterPersonal));

// Búsqueda y filtrado de clientes
function filterClientes() {
    const searchTerm = document.getElementById('searchClientes').value.toLowerCase();
    const estadoFilter = document.querySelector('input[name="estado_clientes"]:checked').value;

    // Filtrar cards
    const cards = document.querySelectorAll('.client-card');
    let visibleCards = 0;
    cards.forEach(card => {
        const searchData = card.getAttribute('data-search');
        const estadoData = card.getAttribute('data-estado');
        const matchesSearch = !searchTerm || (searchData && searchData.includes(searchTerm));
        const matchesEstado = !estadoFilter || estadoData === estadoFilter;

        if (matchesSearch && matchesEstado) {
            card.style.display = '';
            visibleCards++;
        } else {
            card.style.display = 'none';
        }
    });

    const clientesGrid = document.getElementById('clientesGrid');
    let clientesGridEmpty = document.getElementById('clientesGridEmptyMsg');
    if (visibleCards === 0 && cards.length > 0) {
        if (!clientesGridEmpty) {
            clientesGridEmpty = document.createElement('div');
            clientesGridEmpty.id = 'clientesGridEmptyMsg';
            clientesGridEmpty.className = 'empty-state empty-state-filter';
            clientesGridEmpty.innerHTML = '<div class="empty-icon"><i class="fas fa-search"></i></div><h3>Sin resultados</h3><p>No se encontraron clientes que coincidan con la búsqueda o filtros aplicados.</p>';
            clientesGrid.appendChild(clientesGridEmpty);
        }
        clientesGridEmpty.style.display = 'block';
    } else if (clientesGridEmpty) {
        clientesGridEmpty.style.display = 'none';
    }

    // Filtrar tabla
    const tableRows = document.querySelectorAll('#clientesTable tbody tr:not(#clientesTableEmptyMsg)');
    let visibleRows = 0;
    tableRows.forEach(row => {
        const searchData = row.getAttribute('data-search');
        const estadoData = row.getAttribute('data-estado');
        const matchesSearch = !searchTerm || (searchData && searchData.includes(searchTerm));
        const matchesEstado = !estadoFilter || estadoData === estadoFilter;

        if (matchesSearch && matchesEstado) {
            row.style.display = '';
            visibleRows++;
        } else {
            row.style.display = 'none';
        }
    });

    const clientesTableBody = document.querySelector('#clientesTable tbody');
    let clientesTableEmpty = document.getElementById('clientesTableEmptyMsg');
    if (visibleRows === 0 && tableRows.length > 0) {
        if (!clientesTableEmpty) {
            clientesTableEmpty = document.createElement('tr');
            clientesTableEmpty.id = 'clientesTableEmptyMsg';
            clientesTableEmpty.className = 'empty-table-row';
            clientesTableEmpty.innerHTML = '<td colspan="7"><div class="empty-state empty-state-filter"><div class="empty-icon"><i class="fas fa-search"></i></div><h3>Sin resultados</h3><p>No se encontraron clientes que coincidan con la búsqueda o filtros aplicados.</p></div></td>';
            clientesTableBody.appendChild(clientesTableEmpty);
        }
        clientesTableEmpty.style.display = '';
    } else if (clientesTableEmpty) {
        clientesTableEmpty.style.display = 'none';
    }
}

document.getElementById('searchClientes').addEventListener('keyup', filterClientes);
document.querySelectorAll('input[name="estado_clientes"]').forEach(radio => radio.addEventListener('change', filterClientes));
</script>

<!-- Modal crear / editar usuario -->
<div id="modalUsuarioGestion" class="users-modal" style="display:none;" onclick="if(event.target===this) cerrarModalUsuario()">
    <div class="modal-content users-modal__panel">
        <div class="modal-header">
            <h3 id="modalUsuarioTitle"><i class="fas fa-user-plus"></i> Nuevo Usuario</h3>
            <button type="button" class="close-modal" onclick="cerrarModalUsuario()" aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="formUsuarioGestion" onsubmit="guardarUsuarioForm(event)">
            <div class="users-modal__body">
                <div class="form-grid">
                    <div class="input-group" id="group_tipo_doc">
                        <label>Tipo de documento</label>
                        <select name="tipo_documento" id="user_tipo_doc" required onchange="validarSelect(this)">
                            <option value="">Seleccione...</option>
                            <option value="CC">Cédula de Ciudadanía</option>
                            <option value="TI">Tarjeta de Identidad</option>
                            <option value="CE">Cédula de Extranjería</option>
                            <option value="PP">Pasaporte</option>
                        </select>
                        <span class="error-message"></span>
                    </div>

                    <div class="input-group" id="group_doc">
                        <label>N° Documento</label>
                        <input type="text" name="documento" id="user_doc" required
                               placeholder="Ej: 1023456789" maxlength="20"
                               oninput="validarDocumento(this)">
                        <input type="hidden" name="original_doc" id="user_original_doc">
                        <span class="error-message"></span>
                    </div>

                    <div class="input-group" id="group_rol">
                        <label>Rol</label>
                        <select name="id_rol" id="user_rol" required onchange="validarSelect(this)">
                            <option value="">Seleccione...</option>
                            <option value="1">Administrador</option>
                            <option value="2">Veterinario</option>
                            <option value="3" hidden disabled>Recepcionista</option>
                            <option value="4">Propietario / Cliente</option>
                        </select>
                        <span class="error-message"></span>
                    </div>

                    <div class="input-group" style="grid-column: span 2;">
                        <label>Nombre completo</label>
                        <input type="text" name="nombre_completo" id="user_nombre" required
                               placeholder="Nombre y apellidos" maxlength="100"
                               oninput="validarNombre(this)">
                        <span class="error-message"></span>
                    </div>

                    <div class="input-group">
                        <label>Correo electrónico</label>
                        <input type="email" name="email" id="user_email" required placeholder="correo@ejemplo.com" maxlength="100"
                               oninput="validarEmail(this)">
                        <span class="error-message"></span>
                    </div>

                    <div class="input-group">
                        <label>Teléfono</label>
                        <input type="text" name="telefono" id="user_tel" required
                               placeholder="Ej: 3001234567" maxlength="15"
                               oninput="validarTelefono(this)">
                        <span class="error-message"></span>
                    </div>

                    <div class="input-group" id="user_estado_group">
                        <label>Estado</label>
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-top: 0.5rem; height: 100%;">
                            <label class="toggle-switch">
                                <input type="checkbox" id="user_estado_toggle" checked onchange="
                                    document.getElementById('user_estado_hidden').value = this.checked ? '1' : '0';
                                    const t = document.getElementById('user_estado_text');
                                    t.textContent = this.checked ? 'Activo' : 'Inactivo';
                                    t.style.color = this.checked ? '#10B981' : '#EF4444';
                                ">
                                <span class="toggle-slider"></span>
                            </label>
                            <span id="user_estado_text" style="font-size: 0.95rem; font-weight: 700; color: #10B981;">Activo</span>
                        </div>
                        <input type="hidden" name="estado" id="user_estado_hidden" value="1">
                        <span class="error-message"></span>
                    </div>

                    <div class="input-group" style="grid-column: span 2;" id="user_pass_group">
                        <label>Contraseña <span style="font-size: 0.75rem; color: #6B7280; font-weight: normal;">(Se asignará automáticamente al crear usuario)</span></label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="user_pass" minlength="8" maxlength="72"
                                   placeholder="Mínimo 8 caracteres, con mayúscula, minúscula y número"
                                   oninput="validarPassword(this)">
                            <button type="button" class="toggle-password-btn" onclick="togglePasswordUsuario()">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                        <span class="error-message"></span>
                    </div>
                </div>
            </div>
        </form>

        <div class="users-modal__footer">
            <button type="button" class="btn-modal-secondary" onclick="cerrarModalUsuario()">Cancelar</button>
            <button type="button" class="btn-modal-primary" id="btnGuardarUsuario" onclick="document.getElementById('formUsuarioGestion').requestSubmit()">
                <i class="fas fa-save"></i> Crear Usuario
            </button>
        </div>
    </div>
</div>

<!-- Modal detalle cliente -->
<div id="modalDetalleCliente" class="users-modal" style="display:none;" onclick="if(event.target===this) cerrarModalCliente()">
    <div class="modal-content users-modal__panel" style="max-width: 600px;">
        <div class="modal-header">
            <h3 id="modalClienteTitle"><i class="fas fa-user"></i> Detalle del Cliente</h3>
            <button type="button" class="close-modal" onclick="cerrarModalCliente()" aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="users-modal__body" id="modalClienteBody">
            <!-- El contenido se cargará dinámicamente -->
        </div>
    </div>
</div>

<script>
// Almacenar datos de clientes en JavaScript
const clientesData = <?php echo json_encode($propietarios ?? []); ?>;

function verDetalleCliente(documento) {
    const cliente = clientesData.find(c => c.documento == documento);
    if (!cliente) {
        Swal.fire('Error', 'No se encontraron datos del cliente', 'error');
        return;
    }

    const modalBody = document.getElementById('modalClienteBody');
    const mascotasHtml = cliente.mascotas && cliente.mascotas.length > 0 
        ? cliente.mascotas.map(m => {
            const tieneFoto = m.url_foto && m.url_foto.trim() !== '';
            const fotoMascota = tieneFoto ? `uploads/mascotas/${m.url_foto}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(m.nombre)}&background=10B981&color=fff&size=64`;
            const onClickHandler = tieneFoto ? `onclick="viewImage(this.src)"` : '';
            const cursorStyle = tieneFoto ? 'cursor: pointer;' : 'cursor: default;';
            return `
            <div class="mascota-item">
                <div class="mascota-avatar">
                    <img src="${fotoMascota}" alt="${m.nombre}" ${onClickHandler} onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(m.nombre)}&background=10B981&color=fff&size=64'; this.style.cursor='default';" style="${cursorStyle}">
                </div>
                <div class="mascota-info">
                    <strong>${m.nombre}</strong>
                    <span>${m.nombre_especie || m.especie || 'No especificada'}</span>
                    <span>${m.nombre_raza || m.raza || 'No especificada'}</span>
                </div>
                <div class="mascota-status status-${m.estado == 1 ? 'active' : 'inactive'}"></div>
            </div>
        `}).join('')
        : '<div class="no-mascotas"><i class="fas fa-paw"></i><span>Este cliente no tiene mascotas registradas</span></div>';

    modalBody.innerHTML = `
        <div class="cliente-detail-header">
            <div class="cliente-detail-avatar">
                <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(cliente.nombre_completo)}&background=5560FF&color=fff&size=128" alt="${cliente.nombre_completo}">
            </div>
            <div class="cliente-detail-info">
                <h2>${cliente.nombre_completo}</h2>
                <p class="cliente-detail-doc">${cliente.documento}</p>
                <div class="cliente-detail-contact">
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <span>${cliente.email || 'No registrado'}</span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <span>${cliente.telefono || 'No registrado'}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="cliente-detail-mascotas">
            <h4><i class="fas fa-paw"></i> Mascotas (${cliente.num_mascotas})</h4>
            <div class="mascotas-list">
                ${mascotasHtml}
            </div>
        </div>
    `;

    document.getElementById('modalDetalleCliente').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function cerrarModalCliente() {
    document.getElementById('modalDetalleCliente').style.display = 'none';
    document.body.style.overflow = '';
}

// Validaciones en tiempo real
function validarSelect(select) {
    const errorSpan = select.parentElement.querySelector('.error-message');
    if (!errorSpan) return;
    
    if (select.value === '' || select.value === null) {
        errorSpan.textContent = 'Debe seleccionar una opción';
        errorSpan.style.display = 'block';
        select.classList.add('error');
    } else {
        errorSpan.style.display = 'none';
        select.classList.remove('error');
    }
}

async function verificarDocumentoExiste(input) {
    const value = input.value.replace(/[^0-9]/g, '');
    input.value = value;
    
    const errorSpan = input.parentElement.querySelector('.error-message');
    if (!errorSpan) return;
    
    if (value.length < 5) {
        return; // No verificar si es muy corto
    }
    
    const originalDoc = document.getElementById('user_original_doc').value;
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
    
    const errorSpan = input.parentElement.querySelector('.error-message');
    if (!errorSpan) return;
    
    if (!value || !value.includes('@')) {
        return; // No verificar si es muy corto o no tiene @
    }
    
    const originalDoc = document.getElementById('user_original_doc').value;
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
    const value = input.value.replace(/[^0-9]/g, '');
    input.value = value;
    
    const errorSpan = input.parentElement.querySelector('.error-message');
    if (!errorSpan) return;
    
    if (value.length > 0 && value.length < 5) {
        errorSpan.textContent = 'El documento debe tener al menos 5 dígitos';
        errorSpan.style.display = 'block';
        input.classList.add('error');
    } else if (value.length > 20) {
        errorSpan.textContent = 'El documento no puede tener más de 20 dígitos';
        errorSpan.style.display = 'block';
        input.classList.add('error');
    } else {
        errorSpan.style.display = 'none';
        input.classList.remove('error');
    }
    
    // Verificar si el documento ya existe
    if (value.length >= 5) {
        verificarDocumentoExiste(input);
    }
}

function validarNombre(input) {
    const value = input.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '');
    input.value = value;
    
    const errorSpan = input.parentElement.querySelector('.error-message');
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
    
    const errorSpan = input.parentElement.querySelector('.error-message');
    if (!errorSpan) return;
    
    // Regex más estricto: el dominio final solo permite de 2 a 4 letras (ej. .co, .com, .net, .info)
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
    
    // Verificar si el email ya existe si es válido
    if (emailRegex.test(value) && value.length > 0) {
        verificarEmailExiste(input);
    }
}

function validarTelefono(input) {
    // Evitar letras, permitiendo formato (números, espacios, +, guiones, paréntesis)
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
    
    if (typeof itiPhone !== 'undefined') {
        if (itiPhone.isValidNumber()) {
            errorSpan.style.display = 'none';
            input.classList.remove('error');
        } else {
            const errorMsgMap = ["Número inválido", "Código de país inválido", "Demasiado corto", "Demasiado largo", "Número inválido"];
            const errorCode = itiPhone.getValidationError();
            const msg = (errorCode >= 0 && errorCode < errorMsgMap.length) ? errorMsgMap[errorCode] : "El número no es válido para este país";
            errorSpan.textContent = msg;
            errorSpan.style.display = 'block';
            input.classList.add('error');
        }
    }
}

function validarPassword(input) {
    const value = input.value;
    
    const errorSpan = input.parentElement.parentElement.querySelector('.error-message');
    if (!errorSpan) return;
    
    const motivoPwd = value.length > 0 ? window.motivoPasswordInvalida(value) : null;
    if (motivoPwd !== null) {
        errorSpan.textContent = motivoPwd;
        errorSpan.style.display = 'block';
        input.classList.add('error');
    } else if (value.length > 72) {
        errorSpan.textContent = 'La contraseña no puede tener más de 72 caracteres';
        errorSpan.style.display = 'block';
        input.classList.add('error');
    } else {
        errorSpan.style.display = 'none';
        input.classList.remove('error');
    }
}

function viewImage(src) {
    const lb = document.getElementById('lightboxVisor');
    if (lb) { lb.querySelector('img').src = src; lb.style.display = 'flex'; }
}

function closeLightbox() {
    const lb = document.getElementById('lightboxVisor');
    if (lb) lb.style.display = 'none';
}
</script>

<!-- Lightbox para ver fotos de mascotas -->
<div id="lightboxVisor" class="lightbox" onclick="closeLightbox()">
    <img src="" alt="Vista previa">
</div>

<style>
.lightbox {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    z-index: 20000;
    justify-content: center;
    align-items: center;
    cursor: pointer;
}

.lightbox img {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
}
</style>


<style>
.cliente-detail-header {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-color);
}

.cliente-detail-avatar img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.cliente-detail-info h2 {
    margin: 0 0 0.5rem 0;
    font-size: 1.5rem;
    color: var(--text-primary);
}

.cliente-detail-doc {
    margin: 0 0 1rem 0;
    font-size: 0.9rem;
    color: var(--text-muted);
    background: var(--bg-main);
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    display: inline-block;
}

.cliente-detail-contact {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.cliente-detail-mascotas h4 {
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.cliente-detail-mascotas h4 i {
    color: var(--primary);
}
</style>
