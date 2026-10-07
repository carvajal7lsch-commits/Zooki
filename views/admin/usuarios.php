<?php
/**
 * HU-T.7 — Personal de la clínica activa y propietarios vinculados a ella.
 *
 * Recibe del enrutador $personal y $propietarios (UsuarioController), ya
 * acotados a la clínica del contexto. El alta y la edición de propietarios y
 * sus mascotas llegan con el módulo 1 (C3): aquí solo se listan.
 * El comportamiento está en js/usuarios.js; los datos viajan en data-*.
 */
require_once __DIR__ . '/../../helpers/Roles.php';
require_once __DIR__ . '/../../helpers/Contexto.php';
require_once __DIR__ . '/../../models/Usuario.php';

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$yo = Contexto::idUsuario();
$contexto = Contexto::actual();
?>
<div class="users-container" id="usuariosModulo">
    <div class="header-container-white">
        <div class="head-title-desc">
            <h1 class="users-page-title">Gestión de usuarios</h1>
            <p class="users-module-desc">
                Personal de <?= $e($contexto['clinica'] ?? 'la clínica') ?> y propietarios vinculados a ella.
            </p>
        </div>
        <div class="head-top-right">
            <div class="tabs-wrapper" role="tablist">
                <button type="button" class="tab-btn active" data-tab="personal" role="tab" aria-selected="true">
                    <i class="fas fa-users-cog" aria-hidden="true"></i><span>Personal</span>
                </button>
                <button type="button" class="tab-btn" data-tab="propietarios" role="tab" aria-selected="false">
                    <i class="fas fa-user-friends" aria-hidden="true"></i><span>Propietarios</span>
                </button>
            </div>
            <button type="button" class="btn-create btn-create-large" data-accion="nuevo">
                <i class="fas fa-plus" aria-hidden="true"></i><span>Nuevo integrante</span>
            </button>
        </div>
        <div class="head-search">
            <div class="search-input">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search" id="usuariosBuscar" placeholder="Buscar por nombre, documento o correo" aria-label="Buscar">
            </div>
        </div>
    </div>

    <!-- Personal de la clínica -->
    <section class="tab-panel active" data-panel="personal">
        <div class="table-view">
            <table class="users-table">
                <thead>
                    <tr><th>Persona</th><th>Documento</th><th>Contacto</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                    <?php if (!$personal): ?>
                        <tr class="empty-table-row"><td colspan="6">Todavía no hay personal registrado.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($personal as $p): ?>
                        <?php $activo = (int) $p['estado_clinica'] === 1; $esYo = (int) $p['id_usuario'] === $yo; ?>
                        <tr data-fila data-buscar="<?= $e(mb_strtolower($p['nombre_completo'] . ' ' . $p['documento'] . ' ' . $p['email'])) ?>">
                            <td><strong><?= $e($p['nombre_completo']) ?></strong><?= $esYo ? ' <small>(tú)</small>' : '' ?></td>
                            <td><?= $e(trim($p['tipo_documento'] . ' ' . $p['documento'])) ?></td>
                            <td><?= $e($p['email']) ?><br><small><?= $e($p['telefono'] ?? '') ?></small></td>
                            <td><span class="role-badge role-<?= (int) $p['id_rol'] ?>"><?= $e(Roles::nombre((int) $p['id_rol'])) ?></span></td>
                            <td><span class="status-badge <?= $activo ? 'status-active' : 'status-inactive' ?>"><?= $activo ? 'Activo' : 'Inactivo' ?></span></td>
                            <td class="table-actions">
                                <button type="button" class="action-btn" data-accion="editar" data-id="<?= (int) $p['id_usuario'] ?>" title="Editar" aria-label="Editar"><i class="fas fa-pen" aria-hidden="true"></i></button>
                                <?php if (!$esYo): ?>
                                    <button type="button" class="action-btn" data-accion="estado" data-id="<?= (int) $p['id_usuario'] ?>" data-estado="<?= $activo ? 0 : 1 ?>" data-nombre="<?= $e($p['nombre_completo']) ?>" title="<?= $activo ? 'Desactivar en la clínica' : 'Activar en la clínica' ?>" aria-label="<?= $activo ? 'Desactivar' : 'Activar' ?>"><i class="fas <?= $activo ? 'fa-user-slash' : 'fa-user-check' ?>" aria-hidden="true"></i></button>
                                    <button type="button" class="action-btn" data-accion="restablecer" data-id="<?= (int) $p['id_usuario'] ?>" data-nombre="<?= $e($p['nombre_completo']) ?>" title="Restablecer contraseña" aria-label="Restablecer contraseña"><i class="fas fa-key" aria-hidden="true"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Propietarios vinculados (solo lectura en C1) -->
    <section class="tab-panel" data-panel="propietarios" hidden>
        <div class="table-view">
            <table class="users-table">
                <thead>
                    <tr><th>Propietario</th><th>Documento</th><th>Contacto</th><th>Mascotas en la clínica</th><th>Vínculo</th></tr>
                </thead>
                <tbody>
                    <?php if (!$propietarios): ?>
                        <tr class="empty-table-row"><td colspan="5">Todavía no hay propietarios vinculados a la clínica.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($propietarios as $p): ?>
                        <tr data-fila data-buscar="<?= $e(mb_strtolower($p['nombre_completo'] . ' ' . $p['documento'] . ' ' . $p['email'])) ?>">
                            <td><strong><?= $e($p['nombre_completo']) ?></strong></td>
                            <td><?= $e(trim(($p['tipo_documento'] ?? '') . ' ' . ($p['documento'] ?? ''))) ?></td>
                            <td><?= $e($p['email']) ?><br><small><?= $e($p['telefono'] ?? '') ?></small></td>
                            <td><?= (int) $p['num_mascotas'] ?></td>
                            <td><span class="status-badge <?= (int) $p['estado_clinica'] === 1 ? 'status-active' : 'status-inactive' ?>"><?= (int) $p['estado_clinica'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<!-- Alta y edición de personal -->
<div class="users-modal" id="usuarioModal" role="dialog" aria-modal="true" aria-labelledby="usuarioModalTitulo">
    <div class="modal-content users-modal__panel">
        <div class="modal-header">
            <h3 id="usuarioModalTitulo"><i class="fas fa-user-plus" aria-hidden="true"></i> <span data-titulo>Nuevo integrante</span></h3>
        </div>
        <form id="usuarioForm" novalidate>
            <input type="hidden" name="id_usuario" value="">
            <div class="users-modal__body">
                <p class="modal-subtitle" data-aviso hidden></p>
                <div class="form-grid">
                    <div class="input-group">
                        <label for="usuarioTipoDoc">Tipo doc.</label>
                        <select id="usuarioTipoDoc" name="tipo_documento" required>
                            <?php foreach (Usuario::TIPOS_DOCUMENTO as $tipo): ?>
                                <option value="<?= $e($tipo) ?>"><?= $e($tipo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="usuarioDocumento">Documento</label>
                        <input type="text" id="usuarioDocumento" name="documento" required inputmode="numeric" pattern="\d{5,15}" maxlength="15">
                    </div>
                    <div class="input-group">
                        <label for="usuarioRol">Rol en la clínica</label>
                        <select id="usuarioRol" name="id_rol" required>
                            <?php foreach (Roles::DE_CLINICA as $rol): ?>
                                <option value="<?= $rol ?>"><?= $e(Roles::nombre($rol)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-group full">
                        <label for="usuarioNombre">Nombre completo</label>
                        <input type="text" id="usuarioNombre" name="nombre_completo" required minlength="3" maxlength="100">
                    </div>
                    <div class="input-group">
                        <label for="usuarioEmail">Correo</label>
                        <input type="email" id="usuarioEmail" name="email" required maxlength="255">
                    </div>
                    <div class="input-group">
                        <label for="usuarioTelefono">Teléfono</label>
                        <input type="tel" id="usuarioTelefono" name="telefono" maxlength="20">
                    </div>
                    <div class="input-group">
                        <label for="usuarioEstado">Estado en la clínica</label>
                        <select id="usuarioEstado" name="estado">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>
                    <div class="input-group full" data-solo-alta>
                        <label for="usuarioPassword">Contraseña inicial (opcional)</label>
                        <input type="password" id="usuarioPassword" name="password" minlength="8" maxlength="72" autocomplete="new-password">
                        <small>Si la dejas vacía se genera una temporal y se envía al correo. En ambos casos se pide cambiarla al entrar.</small>
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

<script src="js/usuarios.js?v=1"></script>
