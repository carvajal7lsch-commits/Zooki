<?php
/* ── Helpers de presentación ───────────────────────────────── */
$rolColors = [
    "administrador" => [
        "bg" => "rgba(85,96,255,.14)",
        "color" => "#5560FF",
        "badge_bg" => "#EEF2FF",
        "badge_text" => "#3730A3",
    ],
    "veterinario" => [
        "bg" => "rgba(16,185,129,.14)",
        "color" => "#10B981",
        "badge_bg" => "#DCFCE7",
        "badge_text" => "#166534",
    ],
    "recepcionista" => [
        "bg" => "rgba(245,158,11,.14)",
        "color" => "#D97706",
        "badge_bg" => "#FEF3C7",
        "badge_text" => "#92400E",
    ],
    "propietario" => [
        "bg" => "rgba(139,92,246,.14)",
        "color" => "#8B5CF6",
        "badge_bg" => "#EDE9FE",
        "badge_text" => "#5B21B6",
    ],
];
$defaultColor = [
    "bg" => "#F1F5F9",
    "color" => "#64748B",
    "badge_bg" => "#F1F5F9",
    "badge_text" => "#475569",
];

function getInitials(string $name): string
{
    $parts = array_values(array_filter(explode(" ", $name)));
    $a = strtoupper(substr($parts[0] ?? "", 0, 1));
    $b =
        count($parts) > 1
            ? strtoupper(substr($parts[count($parts) - 1], 0, 1))
            : "";
    return $a . $b;
}

/* ── Conteo por rol para los filtros ───────────────────────── */
$countByRol = [];
foreach ($usuarios as $u) {
    $r = strtolower($u["nombre_rol"]);
    $countByRol[$r] = ($countByRol[$r] ?? 0) + 1;
}
$totalStaff = count($usuarios);
?>

<!-- ══ GESTIÓN DE PERSONAL ══════════════════════════════════════════════ -->
<div class="section-card animate__animated animate__fadeIn">

    <!-- Header -->
    <div class="section-header" style="flex-wrap:wrap;gap:1rem;margin-bottom:1.25rem;">
        <div>
            <h2 style="font-size:1.4rem;">
                <i class="fas fa-users-cog" style="color:#5560FF;margin-right:.45rem;"></i>
                Gestión de Personal
            </h2>
            <p style="color:#7E8494;font-size:.82rem;margin-top:.2rem;">
                Administra el equipo de la clínica · <?= $totalStaff ?> colaboradores
            </p>
        </div>
        <div class="header-actions">
            <div class="search-input-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" id="userSearch" placeholder="Buscar nombre o documento..." onkeyup="filterUsers()">
            </div>
            <div class="view-switcher">
                <button class="view-btn active" id="btnListView" onclick="switchView('list')" title="Vista Lista">
                    <i class="fas fa-list-ul"></i>
                </button>
                <button class="view-btn" id="btnGridView" onclick="switchView('grid')" title="Vista Cuadrícula">
                    <i class="fas fa-th-large"></i>
                </button>
            </div>
            <button class="btn-primary" onclick="abrirModalUsuario()" style="padding:.65rem 1.1rem;font-size:.88rem;gap:.4rem;">
                <i class="fas fa-user-plus"></i>
                <span>Nuevo</span>
            </button>
        </div>
    </div>

    <!-- Filtros por rol -->
    <div class="role-filters">
        <button class="role-tab active" onclick="filterByRole('all')" id="tab-all">
            Todos <span class="role-tab-count"><?= $totalStaff ?></span>
        </button>
        <?php foreach ($countByRol as $rol => $cnt):
            $icon =
                [
                    "administrador" => "fa-shield-alt",
                    "veterinario" => "fa-stethoscope",
                    "recepcionista" => "fa-concierge-bell",
                ][$rol] ?? "fa-user"; ?>
        <button class="role-tab" onclick="filterByRole('<?= $rol ?>')" id="tab-<?= $rol ?>" data-role="<?= $rol ?>">
            <i class="fas <?= $icon ?>" style="font-size:.72rem;"></i>
            <?= ucfirst($rol) ?> <span class="role-tab-count"><?= $cnt ?></span>
        </button>
        <?php
        endforeach; ?>
    </div>

    <!-- ── VISTA LISTA (Tabla) ─────────────────────────────────────────── -->
    <div id="listView" class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Colaborador</th>
                    <th>Rol</th>
                    <th>Contacto</th>
                    <th style="text-align:center;">Estado</th>
                    <th style="text-align:center;">Acciones</th>
                </tr>
            </thead>
            <tbody id="userTableBody">
                <?php foreach ($usuarios as $u):

                    $rc =
                        $rolColors[strtolower($u["nombre_rol"])] ??
                        $defaultColor;
                    $ini = getInitials($u["nombre_completo"]);
                    ?>
                <tr class="user-row staff-row"
                    data-name="<?= strtolower($u["nombre_completo"]) ?>"
                    data-doc="<?= $u["documento"] ?>"
                    data-role="<?= strtolower($u["nombre_rol"]) ?>">

                    <td>
                        <div class="staff-name-cell">
                            <div class="staff-avatar"
                                 style="background:<?= $rc[
                                     "bg"
                                 ] ?>;color:<?= $rc["color"] ?>;">
                                <?= $ini ?>
                            </div>
                            <div class="staff-name-info">
                                <strong><?= htmlspecialchars(
                                    $u["nombre_completo"],
                                ) ?></strong>
                                <small><?= $u["tipo_documento"] ?> <?= $u[
     "documento"
 ] ?></small>
                            </div>
                        </div>
                    </td>

                    <td>
                        <span class="status-badge"
                              style="background:<?= $rc[
                                  "badge_bg"
                              ] ?>;color:<?= $rc["badge_text"] ?>;">
                            <?= ucfirst($u["nombre_rol"]) ?>
                        </span>
                    </td>

                    <td>
                        <div class="staff-contact-cell">
                            <span><i class="fas fa-envelope"></i><?= htmlspecialchars(
                                $u["email"],
                            ) ?></span>
                            <span><i class="fas fa-phone"></i><?= htmlspecialchars(
                                $u["telefono"],
                            ) ?></span>
                        </div>
                    </td>

                    <td style="text-align:center;">
                        <label class="toggle-switch"
                               title="<?= $u["estado"] == 1
                                   ? "Desactivar usuario"
                                   : "Activar usuario" ?>">
                            <input type="checkbox"
                                   <?= $u["estado"] == 1 ? "checked" : "" ?>
                                   onchange="toggleUserStatus('<?= $u[
                                       "documento"
                                   ] ?>', this.checked ? 1 : 0)">
                            <span class="toggle-slider"></span>
                        </label>
                    </td>

                    <td style="text-align:center;">
                        <button class="btn-icon-square"
                                onclick="editarUsuario('<?= $u[
                                    "documento"
                                ] ?>')"
                                title="Editar colaborador">
                            <i class="far fa-edit"></i>
                        </button>
                    </td>
                </tr>
                <?php
                endforeach; ?>
            </tbody>
        </table>

        <!-- Empty state -->
        <div id="emptyState" style="display:none;text-align:center;padding:3rem;color:#94A3B8;">
            <i class="fas fa-user-slash" style="font-size:2rem;opacity:.3;display:block;margin-bottom:.75rem;"></i>
            <p style="font-size:.9rem;margin:0;">No se encontraron colaboradores con ese criterio.</p>
        </div>
    </div>

    <!-- ── VISTA CUADRÍCULA (Cards) ───────────────────────────────────── -->
    <div id="gridView" class="users-grid" style="display:none;">
        <?php foreach ($usuarios as $u):

            $rc = $rolColors[strtolower($u["nombre_rol"])] ?? $defaultColor;
            $ini = getInitials($u["nombre_completo"]);
            ?>
        <div class="user-card user-row"
             data-name="<?= strtolower($u["nombre_completo"]) ?>"
             data-doc="<?= $u["documento"] ?>"
             data-role="<?= strtolower($u["nombre_rol"]) ?>">

            <!-- Card header con avatar y dot de estado -->
            <div class="user-card-header">
                <div class="user-card-avatar-lg"
                     style="background:<?= $rc["bg"] ?>;color:<?= $rc[
    "color"
] ?>;">
                    <?= $ini ?>
                </div>
                <span class="status-dot <?= $u["estado"] == 1
                    ? "active"
                    : "inactive" ?>"
                      title="<?= $u["estado"] == 1
                          ? "Activo"
                          : "Inactivo" ?>"></span>
            </div>

            <!-- Card body -->
            <div class="user-card-body">
                <h3><?= htmlspecialchars($u["nombre_completo"]) ?></h3>
                <span class="user-card-role-badge"
                      style="background:<?= $rc["badge_bg"] ?>;color:<?= $rc[
    "badge_text"
] ?>;">
                    <?= ucfirst($u["nombre_rol"]) ?>
                </span>
                <div class="user-card-contact">
                    <span><i class="fas fa-envelope"></i><?= htmlspecialchars(
                        $u["email"],
                    ) ?></span>
                    <span><i class="fas fa-phone"></i><?= htmlspecialchars(
                        $u["telefono"],
                    ) ?></span>
                    <span><i class="fas fa-id-card"></i><?= $u[
                        "tipo_documento"
                    ] ?> <?= $u["documento"] ?></span>
                </div>
            </div>

            <!-- Card footer -->
            <div class="user-card-footer">
                <label class="toggle-switch" title="<?= $u["estado"] == 1
                    ? "Desactivar"
                    : "Activar" ?>">
                    <input type="checkbox"
                           <?= $u["estado"] == 1 ? "checked" : "" ?>
                           onchange="toggleUserStatus('<?= $u[
                               "documento"
                           ] ?>', this.checked ? 1 : 0)">
                    <span class="toggle-slider"></span>
                </label>
                <button class="btn-icon-square"
                        onclick="editarUsuario('<?= $u["documento"] ?>')"
                        title="Editar">
                    <i class="far fa-edit"></i>
                </button>
            </div>
        </div>
        <?php
        endforeach; ?>

        <!-- Empty state grid -->
        <div id="emptyStateGrid" style="display:none;grid-column:1/-1;text-align:center;padding:3rem;color:#94A3B8;">
            <i class="fas fa-user-slash" style="font-size:2rem;opacity:.3;display:block;margin-bottom:.75rem;"></i>
            <p style="font-size:.9rem;margin:0;">No se encontraron colaboradores con ese criterio.</p>
        </div>
    </div>

</div><!-- /section-card -->


<!-- ══ MODAL NUEVO / EDITAR USUARIO ════════════════════════════════════ -->
<div id="modalUsuario" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle"><i class="fas fa-user-plus" style="color:#5560FF;"></i> Nuevo Colaborador</h3>
            <div class="close-modal" onclick="cerrarModalUsuario()"><i class="fas fa-times"></i></div>
        </div>

        <form id="formUsuario" onsubmit="guardarUsuario(event)">
            <div class="form-grid">

                <!-- Sección: Identificación -->
                <div class="modal-section-title">
                    <i class="fas fa-id-card" style="margin-right:.4rem;color:#5560FF;"></i> Identificación
                </div>

                <div class="input-group">
                    <label>Tipo de Documento</label>
                    <select name="tipo_documento" id="user_tipo_doc" required>
                        <option value="CC">Cédula de Ciudadanía</option>
                        <option value="CE">Cédula de Extranjería</option>
                    </select>
                    <span class="error-message"></span>
                </div>

                <div class="input-group">
                    <label>Número de Documento</label>
                    <input type="text" name="documento" id="user_doc" required
                           minlength="5" maxlength="15" placeholder="Ej: 1023456789"
                           oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                    <input type="hidden" name="original_doc" id="original_doc">
                    <span class="error-message"></span>
                </div>

                <div class="input-group full">
                    <label>Nombre Completo</label>
                    <input type="text" name="nombre_completo" id="user_nombre" required
                           minlength="3" maxlength="100" placeholder="Ej: María González">
                    <span class="error-message"></span>
                </div>

                <!-- Sección: Contacto -->
                <div class="modal-section-title">
                    <i class="fas fa-address-book" style="margin-right:.4rem;color:#5560FF;"></i> Contacto
                </div>

                <div class="input-group">
                    <label>Correo Electrónico</label>
                    <input type="email" name="email" id="user_email" required
                           maxlength="100" placeholder="ejemplo@correo.com">
                    <span class="error-message"></span>
                </div>

                <div class="input-group">
                    <label>Teléfono</label>
                    <input type="text" name="telefono" id="user_tel" required
                           minlength="7" maxlength="15" placeholder="Ej: 3001234567"
                           oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                    <span class="error-message"></span>
                </div>

                <!-- Sección: Acceso -->
                <div class="modal-section-title">
                    <i class="fas fa-lock" style="margin-right:.4rem;color:#5560FF;"></i> Acceso al Sistema
                </div>

                <div class="input-group" id="passGroup">
                    <label>Contraseña</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="user_pass" required
                               minlength="8" maxlength="72"
                               placeholder="Mínimo 8 caracteres, con mayúscula, minúscula y número"
                               style="padding-right:3.5rem;">
                        <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility()">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                    <span class="error-message"></span>
                </div>

                <div class="input-group" id="roleGroup">
                    <label>Rol del Sistema</label>
                    <select name="id_rol" id="user_rol" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r["id_rol"] ?>"><?= ucfirst(
    $r["nombre_rol"],
) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="error-message"></span>
                </div>

                <input type="hidden" name="estado" id="user_estado" value="1">
            </div>

            <button type="submit" class="btn-primary"
                    style="width:100%;margin-top:1.5rem;padding:1rem;border-radius:16px;font-weight:700;font-size:1rem;">
                <i class="fas fa-save" style="margin-right:.4rem;"></i>
                Guardar Colaborador
            </button>
        </form>
    </div>
</div>


<script>
/* ── Estado de filtros ─────────────────────────────────────── */
let _currentRole   = 'all';
let _currentSearch = '';

/* ── Init ──────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    const saved = localStorage.getItem('userViewPreference') || 'grid';
    switchView(saved);
});

/* ── Vista lista / grid ────────────────────────────────────── */
function switchView(view) {
    const list    = document.getElementById('listView');
    const grid    = document.getElementById('gridView');
    const btnList = document.getElementById('btnListView');
    const btnGrid = document.getElementById('btnGridView');

    if (view === 'list') {
        list.style.display = 'block';
        grid.style.display = 'none';
        btnList.classList.add('active');
        btnGrid.classList.remove('active');
    } else {
        list.style.display = 'none';
        grid.style.display = 'grid';
        btnList.classList.remove('active');
        btnGrid.classList.add('active');
    }
    localStorage.setItem('userViewPreference', view);
}

/* ── Filtro por rol ────────────────────────────────────────── */
function filterByRole(role) {
    _currentRole = role;
    document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('active'));
    const active = role === 'all'
        ? document.getElementById('tab-all')
        : document.getElementById('tab-' + role);
    if (active) active.classList.add('active');
    applyFilters();
}

/* ── Filtro por búsqueda ───────────────────────────────────── */
function filterUsers() {
    _currentSearch = document.getElementById('userSearch').value.toLowerCase().trim();
    applyFilters();
}

/* ── Aplicar ambos filtros ─────────────────────────────────── */
function applyFilters() {
    const rows = document.querySelectorAll('.user-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const matchSearch = !_currentSearch
            || row.dataset.name.includes(_currentSearch)
            || row.dataset.doc.includes(_currentSearch);
        const matchRole = _currentRole === 'all' || row.dataset.role === _currentRole;
        const visible   = matchSearch && matchRole;

        if (visible) {
            row.style.display = row.classList.contains('user-card') ? 'flex' : 'table-row';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    /* Empty states */
    const es  = document.getElementById('emptyState');
    const esg = document.getElementById('emptyStateGrid');
    if (es)  es.style.display  = visibleCount === 0 ? 'block' : 'none';
    if (esg) esg.style.display = visibleCount === 0 ? 'block' : 'none';
}

/* ── Toggle estado ─────────────────────────────────────────── */
async function toggleUserStatus(doc, newStatus) {
    const isDeactivate = newStatus === 0;
    const result = await Swal.fire({
        title: isDeactivate ? 'Desactivar usuario' : 'Activar usuario',
        text:  isDeactivate
            ? '¿Seguro que quieres desactivar este colaborador? No podrá iniciar sesión.'
            : '¿Quieres reactivar el acceso de este colaborador?',
        icon:  'question',
        showCancelButton:    true,
        confirmButtonColor:  isDeactivate ? '#EF4444' : '#10B981',
        cancelButtonColor:   '#94A3B8',
        confirmButtonText:   isDeactivate ? 'Sí, desactivar' : 'Sí, activar',
        cancelButtonText:    'Cancelar'
    });

    if (result.isConfirmed) {
        try {
            const res = await (await fetch(
                `index.php?action=cambiar_estado_usuario_ajax&documento=${doc}&estado=${newStatus}`
            )).json();

            if (res.success) {
                Swal.fire({
                    title: '¡Listo!', text: res.message,
                    icon: 'success', confirmButtonColor: '#5560FF'
                }).then(() => location.reload());
            } else {
                Swal.fire({ title: 'Error', text: res.message, icon: 'error', confirmButtonColor: '#EF4444' });
                revertToggles(doc, newStatus);
            }
        } catch(e) {
            console.error(e);
            revertToggles(doc, newStatus);
        }
    } else {
        revertToggles(doc, newStatus);
    }
}

function revertToggles(doc, failedStatus) {
    document.querySelectorAll(`.user-row[data-doc="${doc}"] .toggle-switch input`).forEach(t => {
        t.checked = failedStatus === 0; /* Revert: si intentó desactivar (0), vuelve a checked */
    });
}

/* ── Password toggle ───────────────────────────────────────── */
function togglePasswordVisibility() {
    const input   = document.getElementById('user_pass');
    const icon    = document.querySelector('.toggle-password-btn i');
    input.type    = input.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
}

/* ── Abrir / cerrar modal ──────────────────────────────────── */
function abrirModalUsuario() {
    document.getElementById('modalTitle').innerHTML =
        '<i class="fas fa-user-plus" style="color:#5560FF;"></i> Nuevo Colaborador';
    document.getElementById('formUsuario').reset();
    document.getElementById('original_doc').value  = '';
    document.getElementById('user_doc').disabled   = false;
    document.getElementById('passGroup').style.display = 'block';
    document.getElementById('roleGroup').classList.remove('full');
    document.getElementById('user_pass').required  = true;
    document.querySelectorAll('.error-message').forEach(e => { e.style.display='none'; e.textContent=''; });
    document.querySelectorAll('.input-group input, .input-group select').forEach(i => i.classList.remove('error'));
    document.getElementById('modalUsuario').style.display = 'flex';
}

function cerrarModalUsuario() {
    document.getElementById('modalUsuario').style.display = 'none';
}

/* ── Validación ────────────────────────────────────────────── */
function showError(id, msg) {
    const input = document.getElementById(id);
    if (!input) return;
    const grp  = input.closest('.input-group') || input.closest('.password-wrapper')?.closest('.input-group');
    if (!grp)  return;
    input.classList.add('error');
    const sp = grp.querySelector('.error-message');
    if (sp) { sp.textContent = msg; sp.style.display = 'block'; }
}

function clearError(id) {
    const input = document.getElementById(id);
    if (!input) return;
    const grp = input.closest('.input-group') || input.closest('.password-wrapper')?.closest('.input-group');
    if (!grp) return;
    input.classList.remove('error');
    const sp = grp.querySelector('.error-message');
    if (sp) { sp.textContent = ''; sp.style.display = 'none'; }
}

function validateField(id) {
    const input = document.getElementById(id);
    if (!input) return true;
    const val    = input.value.trim();
    const isEdit = document.getElementById('original_doc').value !== '';
    clearError(id);

    if (id === 'user_doc'    && val.length < 5)  { showError(id, 'Mínimo 5 dígitos.'); return false; }
    if (id === 'user_nombre') {
        if (val.length < 3)                              { showError(id, 'Nombre muy corto.');          return false; }
        if (!/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/.test(val))   { showError(id, 'Solo letras y espacios.');    return false; }
    }
    if (id === 'user_email'  && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) { showError(id, 'Email no válido.'); return false; }
    if (id === 'user_tel'    && val.length < 7)  { showError(id, 'Mínimo 7 dígitos.'); return false; }
    if (id === 'user_pass'   && !isEdit) {
        const motivoPwd = window.motivoPasswordInvalida(val);
        if (motivoPwd !== null) { showError(id, motivoPwd + '.'); return false; }
    }
    return true;
}

['user_doc','user_nombre','user_email','user_tel','user_pass'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', () => validateField(id));
        el.addEventListener('blur',  () => validateField(id));
    }
});

/* ── Guardar (crear / editar) ──────────────────────────────── */
async function guardarUsuario(e) {
    e.preventDefault();
    const isEdit  = document.getElementById('original_doc').value !== '';
    const fields  = ['user_doc','user_nombre','user_email','user_tel'];
    if (!isEdit) fields.push('user_pass');

    let valid = true;
    fields.forEach(id => { if (!validateField(id)) valid = false; });
    if (!valid) return;

    const action = isEdit ? 'actualizar_usuario_ajax' : 'registrar_usuario_ajax';
    try {
        const res = await (await fetch(`index.php?action=${action}`, {
            method: 'POST', body: new FormData(e.target)
        })).json();

        cerrarModalUsuario();
        if (res.success) {
            Swal.fire({ title: '¡Éxito!', text: res.message, icon: 'success', confirmButtonColor: '#5560FF' })
                .then(() => location.reload());
        } else {
            Swal.fire({ title: 'Error', text: res.message, icon: 'error', confirmButtonColor: '#EF4444' });
        }
    } catch(err) { console.error(err); }
}

/* ── Editar usuario ────────────────────────────────────────── */
async function editarUsuario(doc) {
    try {
        const res = await (await fetch(`index.php?action=get_usuario_ajax&documento=${doc}`)).json();
        if (!res) return;

        document.getElementById('modalTitle').innerHTML =
            '<i class="far fa-edit" style="color:#5560FF;"></i> Editar Colaborador';
        document.getElementById('user_doc').value       = res.documento;
        document.getElementById('user_doc').disabled    = true;
        document.getElementById('original_doc').value   = res.documento;
        document.getElementById('user_tipo_doc').value  = res.tipo_documento;
        document.getElementById('user_nombre').value    = res.nombre_completo;
        document.getElementById('user_email').value     = res.email;
        document.getElementById('user_tel').value       = res.telefono;
        document.getElementById('user_rol').value       = res.id_rol;
        document.getElementById('user_estado').value    = res.estado;

        document.getElementById('passGroup').style.display = 'none';
        document.getElementById('roleGroup').classList.add('full');
        document.getElementById('user_pass').required   = false;

        document.querySelectorAll('.error-message').forEach(e => { e.style.display='none'; e.textContent=''; });
        document.querySelectorAll('.input-group input, .input-group select').forEach(i => i.classList.remove('error'));

        document.getElementById('modalUsuario').style.display = 'flex';
    } catch(e) {
        console.error(e);
        alert('Error al cargar los datos del colaborador.', 'error');
    }
}
</script>
