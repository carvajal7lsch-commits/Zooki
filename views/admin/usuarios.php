<?php
/**
 * HU-T.7 — Personal de la clínica activa y propietarios vinculados a ella.
 *
 * Recibe del enrutador $personal y $propietarios (UsuarioController), ya
 * acotados a la clínica del contexto. C9.1: mismo diseño que la v1.12.0
 * (specs/referencias/usuarios-*-v1.png) con los datos y las reglas de la v2.
 * El comportamiento está en js/usuarios.js; los datos viajan en data-*.
 */
require_once __DIR__ . '/../../helpers/Roles.php';
require_once __DIR__ . '/../../helpers/Contexto.php';
require_once __DIR__ . '/../../helpers/ValidadorTelefono.php';
require_once __DIR__ . '/../../models/Usuario.php';

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$iniciales = static function (string $nombre): string {
    $palabras = preg_split('/\s+/u', trim($nombre), -1, PREG_SPLIT_NO_EMPTY);
    $letras = array_map(static fn ($p) => mb_substr($p, 0, 1), array_slice($palabras, 0, 2));
    return mb_strtoupper(implode('', $letras));
};
$buscable = static fn (array $p): string => mb_strtolower($p['nombre_completo'] . ' ' . ($p['documento'] ?? '') . ' ' . ($p['email'] ?? ''));
$iconoRol = static fn (int $rol): string => $rol === Roles::ADMIN ? 'bi-shield-shaded' : 'bi-heart-pulse';
$yo = Contexto::idUsuario();
$contexto = Contexto::actual();
$telefonoHtml = ValidadorTelefono::atributosHtml();
$nombresDocumento = ['CC' => 'Cédula de Ciudadanía', 'TI' => 'Tarjeta de Identidad', 'CE' => 'Cédula de Extranjería', 'PP' => 'Pasaporte', 'NIT' => 'NIT'];
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.10/build/css/intlTelInput.css">
<div class="users-container" id="usuariosModulo" data-vista="tarjetas">
    <div class="header-container-white">
        <div class="head-title-desc">
            <h1 class="users-page-title">Gestión de Usuarios</h1>
            <p class="users-module-desc">
                Administra las cuentas del personal de <?= $e($contexto['clinica'] ?? 'la clínica') ?> y gestiona a los clientes y sus mascotas.
            </p>
        </div>
        <div class="head-top-right">
            <div class="tabs-wrapper" role="tablist">
                <button type="button" class="tab-btn active" data-tab="personal" role="tab" aria-selected="true">
                    <i class="fas fa-users-cog" aria-hidden="true"></i><span>Personal</span>
                </button>
                <button type="button" class="tab-btn" data-tab="clientes" role="tab" aria-selected="false">
                    <i class="fas fa-user-friends" aria-hidden="true"></i><span>Clientes</span>
                </button>
            </div>
            <button type="button" class="btn-create btn-create-large" data-accion="nuevo">
                <i class="fas fa-plus" aria-hidden="true"></i><span>Nuevo Usuario</span>
            </button>
        </div>

        <div class="users-controls-bar__filters is-active" data-filtros="personal">
            <div class="head-search">
                <div class="search-input">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="search" data-filtro-texto placeholder="Buscar por nombre o documento..." aria-label="Buscar personal">
                </div>
            </div>
            <div class="head-status-filters">
                <select class="filter-select" data-filtro-rol aria-label="Filtrar por rol">
                    <option value="">Todos los roles</option>
                    <?php foreach (Roles::DE_CLINICA as $rol): ?>
                        <option value="<?= $rol ?>"><?= $e(Roles::nombre($rol)) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="segmented-control" role="radiogroup" aria-label="Estado del personal">
                    <input type="radio" name="estado_personal" id="ep_todos" value="" data-filtro-estado checked>
                    <label for="ep_todos">Todos</label>
                    <input type="radio" name="estado_personal" id="ep_activos" value="1" data-filtro-estado>
                    <label for="ep_activos">Activos</label>
                    <input type="radio" name="estado_personal" id="ep_inactivos" value="0" data-filtro-estado>
                    <label for="ep_inactivos">Inactivos</label>
                </div>
            </div>
        </div>

        <div class="users-controls-bar__filters" data-filtros="clientes">
            <div class="head-search">
                <div class="search-input">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="search" data-filtro-texto placeholder="Buscar cliente por nombre o documento..." aria-label="Buscar cliente">
                </div>
            </div>
            <div class="head-status-filters">
                <div class="segmented-control" role="radiogroup" aria-label="Estado del vínculo del cliente">
                    <input type="radio" name="estado_clientes" id="ec_todos" value="" data-filtro-estado checked>
                    <label for="ec_todos">Todos</label>
                    <input type="radio" name="estado_clientes" id="ec_activos" value="1" data-filtro-estado>
                    <label for="ec_activos">Activos</label>
                    <input type="radio" name="estado_clientes" id="ec_inactivos" value="0" data-filtro-estado>
                    <label for="ec_inactivos">Inactivos</label>
                </div>
            </div>
        </div>

        <div class="head-view-toggle">
            <div class="view-toggle" role="group" aria-label="Tipo de vista">
                <button type="button" class="view-toggle-btn" data-vista-usuarios="tabla" aria-pressed="false" title="Vista de tabla"><i class="bi bi-table" aria-hidden="true"></i></button>
                <button type="button" class="view-toggle-btn active" data-vista-usuarios="tarjetas" aria-pressed="true" title="Vista de tarjetas"><i class="bi bi-grid" aria-hidden="true"></i></button>
            </div>
        </div>
    </div>

    <!-- Personal de la clínica -->
    <section class="tab-panel active" data-panel="personal">
        <div class="personal-grid view-grid" data-vista-contenido="tarjetas">
            <?php if (!$personal): ?>
                <div class="empty-state"><div class="empty-icon"><i class="fas fa-users-slash" aria-hidden="true"></i></div><h3>Todavía no hay personal registrado</h3></div>
            <?php endif; ?>
            <?php foreach ($personal as $p): ?>
                <?php
                $activo = (int) $p['estado_clinica'] === 1;
                $esYo = (int) $p['id_usuario'] === $yo;
                $rol = (int) $p['id_rol'];
                ?>
                <article class="person-card <?= $esYo ? 'current-user' : '' ?>" data-fila data-id-usuario="<?= (int) $p['id_usuario'] ?>" data-identidad-editable="<?= $p['identidad_editable'] ? '1' : '0' ?>" data-rol="<?= $rol ?>" data-estado="<?= $activo ? 1 : 0 ?>" data-buscar="<?= $e($buscable($p)) ?>">
                    <div class="card-header-mini">
                        <span class="avatar-mini avatar-iniciales avatar-iniciales--rol-<?= $rol ?>" aria-hidden="true"><?= $e($iniciales($p['nombre_completo'])) ?></span>
                        <div class="status-indicator"><?php require __DIR__ . '/partials/interruptor_personal.php'; ?></div>
                    </div>
                    <div class="card-body-mini">
                        <h3 class="card-title-mini"><?= $e($p['nombre_completo']) ?><?php if ($esYo): ?> <span class="current-user-label">(Tú)</span><?php endif; ?></h3>
                        <div class="card-tags-mini">
                            <?php if ((int) $p['estado'] === 0): ?><span class="tag-mini"><i class="bi bi-envelope" aria-hidden="true"></i> Pendiente de activación</span><?php endif; ?>
                            <?php if (!$activo): ?><span class="tag-mini"><i class="bi bi-moon-stars" aria-hidden="true"></i> Inactivo</span><?php endif; ?>
                            <span class="tag-mini"><i class="bi <?= $iconoRol($rol) ?>" aria-hidden="true"></i> <?= $e(Roles::nombre($rol)) ?></span>
                        </div>
                        <div class="card-contact-mini">
                            <span class="contact-text-mini" title="<?= $e($p['tipo_documento']) ?>"><i class="bi bi-person-badge" aria-hidden="true"></i> <?= $e($p['documento']) ?></span>
                            <?php if (!empty($p['telefono'])): ?>
                                <span class="contact-text-mini"><i class="bi bi-telephone" aria-hidden="true"></i> <?= $e($p['telefono']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-footer-mini">
                        <?php $claseAccion = 'action-btn-mini'; require __DIR__ . '/partials/acciones_personal.php'; ?>
                    </div>
                </article>
            <?php endforeach; ?>
            <div class="empty-state empty-state-filter" data-sin-resultados hidden>
                <div class="empty-icon"><i class="fas fa-search" aria-hidden="true"></i></div>
                <h3>Sin resultados</h3>
                <p>No hay personal que coincida con la búsqueda o los filtros.</p>
            </div>
        </div>

        <div class="table-view" data-vista-contenido="tabla" hidden>
            <table class="users-table">
                <thead>
                    <tr><th>Usuario</th><th>Documento</th><th>Rol</th><th>Email</th><th>Teléfono</th><th>Estado</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                    <?php if (!$personal): ?>
                        <tr class="empty-table-row"><td colspan="7">Todavía no hay personal registrado.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($personal as $p): ?>
                        <?php
                        $activo = (int) $p['estado_clinica'] === 1;
                        $esYo = (int) $p['id_usuario'] === $yo;
                        $rol = (int) $p['id_rol'];
                        ?>
                        <tr data-fila data-rol="<?= $rol ?>" data-estado="<?= $activo ? 1 : 0 ?>" data-buscar="<?= $e($buscable($p)) ?>">
                            <td>
                                <div class="table-user">
                                    <span class="avatar-iniciales avatar-iniciales--tabla avatar-iniciales--rol-<?= $rol ?>" aria-hidden="true"><?= $e($iniciales($p['nombre_completo'])) ?></span>
                                    <div><strong><?= $e($p['nombre_completo']) ?></strong><?php if ($esYo): ?> <span class="current-user-label">(Tú)</span><?php endif; ?></div>
                                </div>
                            </td>
                            <td><?= $e(trim($p['tipo_documento'] . ' ' . $p['documento'])) ?></td>
                            <td><span class="tag-mini"><i class="bi <?= $iconoRol($rol) ?>" aria-hidden="true"></i> <?= $e(Roles::nombre($rol)) ?></span></td>
                            <td><?= $e($p['email']) ?></td>
                            <td><?= $e($p['telefono'] ?? '') ?></td>
                            <td><span class="status-badge <?= $activo && (int) $p['estado'] === 1 ? 'status-active' : 'status-inactive' ?>"><?= (int) $p['estado'] === 0 ? 'Pendiente de activación' : ($activo ? 'Activo' : 'Inactivo') ?></span></td>
                            <td>
                                <div class="table-actions">
                                    <?php require __DIR__ . '/partials/interruptor_personal.php'; ?>
                                    <?php $claseAccion = 'action-btn'; require __DIR__ . '/partials/acciones_personal.php'; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="empty-table-row" data-sin-resultados hidden><td colspan="7">No hay personal que coincida con la búsqueda o los filtros.</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Clientes: propietarios vinculados a la clínica -->
    <section class="tab-panel" data-panel="clientes" hidden>
        <div class="clients-grid view-grid" data-vista-contenido="tarjetas">
            <?php if (!$propietarios): ?>
                <div class="empty-state"><div class="empty-icon"><i class="fas fa-users-slash" aria-hidden="true"></i></div><h3>Todavía no hay clientes vinculados</h3></div>
            <?php endif; ?>
            <?php foreach ($propietarios as $p): ?>
                <?php $activo = (int) $p['estado_clinica'] === 1; ?>
                <article class="client-card person-card" data-fila data-id-usuario="<?= (int) $p['id_usuario'] ?>" data-estado="<?= $activo ? 1 : 0 ?>" data-buscar="<?= $e($buscable($p)) ?>">
                    <div class="card-header-mini">
                        <span class="avatar-mini avatar-iniciales avatar-iniciales--cliente" aria-hidden="true"><?= $e($iniciales($p['nombre_completo'])) ?></span>
                        <div class="status-indicator"><?php require __DIR__ . '/partials/interruptor_cliente.php'; ?></div>
                    </div>
                    <div class="card-body-mini">
                        <h3 class="card-title-mini"><?= $e($p['nombre_completo']) ?></h3>
                        <div class="card-tags-mini">
                            <?php if (!$activo): ?><span class="tag-mini"><i class="bi bi-moon-stars" aria-hidden="true"></i> Inactivo</span><?php endif; ?>
                            <?php if (!empty($p['telefono'])): ?><span class="tag-mini"><i class="bi bi-phone" aria-hidden="true"></i> <?= $e($p['telefono']) ?></span><?php endif; ?>
                        </div>
                        <div class="card-contact-mini">
                            <span class="contact-text-mini" title="<?= $e($p['tipo_documento'] ?? '') ?>"><i class="bi bi-person-badge" aria-hidden="true"></i> <?= $e($p['documento'] ?? '') ?></span>
                        </div>
                    </div>
                    <div class="card-footer-mini">
                        <?php $claseAccion = 'action-btn-mini'; require __DIR__ . '/partials/acciones_cliente.php'; ?>
                    </div>
                </article>
            <?php endforeach; ?>
            <div class="empty-state empty-state-filter" data-sin-resultados hidden>
                <div class="empty-icon"><i class="fas fa-search" aria-hidden="true"></i></div>
                <h3>Sin resultados</h3>
                <p>No hay clientes que coincidan con la búsqueda o los filtros.</p>
            </div>
        </div>

        <div class="table-view" data-vista-contenido="tabla" hidden>
            <table class="users-table">
                <thead>
                    <tr><th>Cliente</th><th>Documento</th><th>Email</th><th>Teléfono</th><th>Mascotas</th><th>Estado</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                    <?php if (!$propietarios): ?>
                        <tr class="empty-table-row"><td colspan="7">Todavía no hay clientes vinculados a la clínica.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($propietarios as $p): ?>
                        <?php $activo = (int) $p['estado_clinica'] === 1; ?>
                        <tr data-fila data-estado="<?= $activo ? 1 : 0 ?>" data-buscar="<?= $e($buscable($p)) ?>">
                            <td>
                                <div class="table-user">
                                    <span class="avatar-iniciales avatar-iniciales--tabla avatar-iniciales--cliente" aria-hidden="true"><?= $e($iniciales($p['nombre_completo'])) ?></span>
                                    <div><strong><?= $e($p['nombre_completo']) ?></strong></div>
                                </div>
                            </td>
                            <td><?= $e(trim(($p['tipo_documento'] ?? '') . ' ' . ($p['documento'] ?? ''))) ?></td>
                            <td><?= $e($p['email']) ?></td>
                            <td><?= $e($p['telefono'] ?? '') ?></td>
                            <td><?= (int) $p['num_mascotas'] ?> mascota(s)</td>
                            <td><span class="status-badge <?= $activo ? 'status-active' : 'status-inactive' ?>"><?= $activo ? 'Activo' : 'Inactivo' ?></span></td>
                            <td>
                                <div class="table-actions">
                                    <?php require __DIR__ . '/partials/interruptor_cliente.php'; ?>
                                    <?php $claseAccion = 'action-btn'; require __DIR__ . '/partials/acciones_cliente.php'; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="empty-table-row" data-sin-resultados hidden><td colspan="7">No hay clientes que coincidan con la búsqueda o los filtros.</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<!-- Alta y edición de personal. D2.1: diseño de v1.12.0 (modalUsuarioGestion,
     specs/referencias/usuarios-modal-editar-v1.png) con las reglas v2: alta sin
     contraseña por invitación de 72 horas e identidad compartida de C1.7. -->
<div class="users-modal" id="usuarioModal" role="dialog" aria-modal="true" aria-labelledby="usuarioModalTitulo" data-yo="<?= (int) $yo ?>">
    <div class="modal-content users-modal__panel">
        <div class="modal-header">
            <h3 id="usuarioModalTitulo">
                <i class="fas fa-user-plus" aria-hidden="true" data-icono-titulo></i>
                <span data-titulo>Nuevo Usuario</span>
                <span class="modal-subtitle" data-subtitulo hidden>
                    <i class="bi bi-person" aria-hidden="true"></i> <span data-subtitulo-nombre></span>
                    &nbsp;&bull;&nbsp;
                    <i class="bi bi-card-text" aria-hidden="true"></i> <span data-subtitulo-documento></span>
                </span>
            </h3>
            <button type="button" class="close-modal" data-accion="cerrar" aria-label="Cerrar">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <form id="usuarioForm" data-cuenta-vincular="true" novalidate data-validacion-cuenta>
            <input type="hidden" name="id_usuario" value="">
            <div class="users-modal__body">
                <p class="modal-aviso" data-aviso hidden></p>
                <div class="form-grid">
                    <div class="input-group" data-solo-alta>
                        <label for="usuarioTipoDoc">Tipo de documento</label>
                        <select id="usuarioTipoDoc" name="tipo_documento" required>
                            <?php foreach (Usuario::TIPOS_DOCUMENTO as $tipo): ?>
                                <option value="<?= $e($tipo) ?>"><?= $e($nombresDocumento[$tipo] ?? $tipo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-group" data-solo-alta>
                        <label for="usuarioDocumento">N° Documento</label>
                        <input type="text" id="usuarioDocumento" name="documento" required inputmode="numeric" pattern="\d{5,15}" maxlength="15" data-caracteres="0-9" placeholder="Ej: 1023456789">
                    </div>
                    <div class="input-group">
                        <label for="usuarioRol">Rol</label>
                        <select id="usuarioRol" name="id_rol" required>
                            <?php foreach (Roles::DE_CLINICA as $rol): ?>
                                <option value="<?= $rol ?>"><?= $e(Roles::nombre($rol)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-group span-2">
                        <label for="usuarioNombre">Nombre completo</label>
                        <input type="text" id="usuarioNombre" name="nombre_completo" required minlength="3" maxlength="100" placeholder="Nombre y apellidos">
                    </div>
                    <div class="input-group">
                        <label for="usuarioEmail">Correo electrónico</label>
                        <input type="email" id="usuarioEmail" name="email" required maxlength="255" placeholder="correo@ejemplo.com">
                    </div>
                    <div class="input-group">
                        <label for="usuarioTelefono">Teléfono</label>
                        <input type="tel" id="usuarioTelefono" name="telefono" placeholder="Ej: 3001234567" <?= $telefonoHtml ?>>
                    </div>
                    <div class="input-group">
                        <label for="usuarioEstadoInterruptor">Estado</label>
                        <div class="estado-usuario">
                            <label class="toggle-switch">
                                <input type="checkbox" id="usuarioEstadoInterruptor" data-estado-interruptor checked>
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="estado-usuario__texto estado-usuario__texto--activo" data-estado-texto>Activo</span>
                        </div>
                        <input type="hidden" name="estado" value="1">
                    </div>
                    <div class="input-group full invitacion-personal" data-solo-alta>
                        <p><i class="fas fa-envelope-open-text" aria-hidden="true"></i> Activación de la cuenta</p>
                        <input type="hidden" id="usuarioPassword" name="password" value="" disabled>
                        <small>El titular recibe un enlace de 72 horas para aceptar la política y crear su contraseña. La cuenta permanece pendiente hasta entonces.</small>
                    </div>
                </div>
            </div>
            <div class="users-modal__footer">
                <button type="button" class="btn-modal-secondary" data-accion="cerrar">Cancelar</button>
                <button type="submit" class="btn-modal-primary"><i class="fas fa-save" aria-hidden="true"></i> <span data-texto-guardar>Crear Usuario</span></button>
            </div>
        </form>
    </div>
</div>

<!-- Edición de un cliente: nombre, teléfono y vínculo con la clínica (C1.7) -->
<div class="users-modal" id="clienteModal" role="dialog" aria-modal="true" aria-labelledby="clienteModalTitulo">
    <div class="modal-content users-modal__panel">
        <div class="modal-header">
            <h3 id="clienteModalTitulo"><i class="fas fa-user-edit" aria-hidden="true"></i> Editar cliente</h3>
        </div>
        <form id="clienteForm" novalidate data-validacion-cuenta>
            <input type="hidden" name="id_usuario" value="">
            <div class="users-modal__body">
                <p class="modal-subtitle">Documento y correo: <strong data-cliente-identidad></strong>. Solo el titular los cambia desde su cuenta.</p>
                <div class="form-grid">
                    <div class="input-group full">
                        <label for="clienteNombre">Nombre completo</label>
                        <input type="text" id="clienteNombre" name="nombre_completo" required minlength="3" maxlength="100">
                    </div>
                    <div class="input-group">
                        <label for="clienteTelefono">Teléfono</label>
                        <input type="tel" id="clienteTelefono" name="telefono" required minlength="<?= ValidadorTelefono::MIN ?>" <?= $telefonoHtml ?>>
                    </div>
                    <div class="input-group">
                        <label for="clienteEstado">Vínculo con la clínica</label>
                        <select id="clienteEstado" name="estado">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                        <small data-ayuda-vinculo hidden>Para reactivarlo usa el interruptor: el propietario lo confirma por correo.</small>
                    </div>
                </div>
            </div>
            <div class="users-modal__footer">
                <button type="button" class="btn-modal-secondary" data-accion="cerrar">Cancelar</button>
                <button type="submit" class="btn-modal-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Detalle de un cliente y sus mascotas en la clínica -->
<div class="users-modal" id="clienteDetalleModal" role="dialog" aria-modal="true" aria-labelledby="clienteDetalleTitulo">
    <div class="modal-content users-modal__panel users-modal__panel--detalle">
        <div class="modal-header">
            <h3 id="clienteDetalleTitulo"><i class="fas fa-user" aria-hidden="true"></i> Detalle del cliente</h3>
        </div>
        <div class="users-modal__body">
            <div class="cliente-detalle">
                <span class="avatar-iniciales avatar-iniciales--cliente avatar-iniciales--grande" data-detalle="iniciales" aria-hidden="true"></span>
                <div class="cliente-detalle__datos">
                    <h4 data-detalle="nombre"></h4>
                    <p><i class="bi bi-person-badge" aria-hidden="true"></i> <span data-detalle="documento"></span></p>
                    <p><i class="bi bi-envelope" aria-hidden="true"></i> <span data-detalle="email"></span></p>
                    <p><i class="bi bi-telephone" aria-hidden="true"></i> <span data-detalle="telefono"></span></p>
                </div>
            </div>
            <h4 class="cliente-detalle__titulo"><i class="fas fa-paw" aria-hidden="true"></i> Mascotas en la clínica</h4>
            <div class="mascotas-list" data-detalle="mascotas"></div>
        </div>
        <div class="users-modal__footer">
            <button type="button" class="btn-modal-secondary" data-accion="cerrar">Cerrar</button>
        </div>
    </div>
</div>

<!-- D2.1: teléfono con bandera y prefijo, el mismo intl-tel-input de Pacientes. -->
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.10/build/js/intlTelInput.min.js"></script>
<script src="js/usuarios.js?v=5-d21"></script>
