<link rel="stylesheet" href="css/gestion.css?v=1">
<link rel="stylesheet" href="css/auditoria.css?v=1">
<?php
/**
 * HU-T.8 — Auditoría de la clínica activa. AuditoriaController entrega $logs,
 * $total, $page, $acciones, $tablas y $stats_hoy, ya filtrados por clínica.
 */
$accionIcons = [
    'LOGIN' => 'fa-sign-in-alt', 'LOGIN_FAIL' => 'fa-user-lock', 'LOGOUT' => 'fa-sign-out-alt',
    'INSERT' => 'fa-plus-circle', 'UPDATE' => 'fa-edit', 'DELETE' => 'fa-trash-alt',
    'VIEW' => 'fa-eye', 'OTHER' => 'fa-cog'
];

// Estadísticas de hoy para los KPIs de seguridad
if (!isset($stats_hoy) || !is_array($stats_hoy)) {
    $stats_hoy = [];
}
$alertas_criticas = ($stats_hoy['DELETE'] ?? 0) + ($stats_hoy['LOGIN_FAIL'] ?? 0);
$intentos_fallidos = $stats_hoy['LOGIN_FAIL'] ?? 0;
$total_acciones = array_sum($stats_hoy);

$perPage = 50; // O el valor que tenga en el controlador
$totalPages = isset($total) ? ceil($total / $perPage) : 1;

if (!isset($acciones) || !is_array($acciones)) $acciones = [];
if (!isset($tablas) || !is_array($tablas)) $tablas = [];
if (!isset($logs) || !is_array($logs)) $logs = [];
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.1/jspdf.plugin.autotable.min.js"></script>

<div class="animate__animated animate__fadeIn">
    <!-- Header de Seguridad -->
    <div class="header-container-white gestion-cabecera">
        <div class="head-title-desc gestion-descripcion">
            <h1 class="users-page-title gestion-titulo"><i class="fas fa-shield-alt gestion-icono-azul"></i> Centro de Seguridad</h1>
            <p class="users-module-desc gestion-subtitulo">Monitorización de actividad y cumplimiento</p>
        </div>
        
        <!-- Mini KPIs Compactos Integrados -->
        <div class="mini-kpis-container gestion-indicadores">
            <div class="mini-kpi">
                <svg id="kpi-eventos-spark" data-auditoria-spark data-valor="<?= (int) $total_acciones ?>" data-color="#0052FF" class="kpi-sparkline" viewBox="0 0 100 30" preserveAspectRatio="none"></svg>
                <div class="mini-kpi-content">
                    <span class="mini-kpi-value" id="kpi-eventos"><?= number_format($total_acciones) ?></span>
                    <span class="mini-kpi-label">Total Eventos</span>
                </div>
            </div>
            <div class="kpi-divider"></div>
            <div class="mini-kpi">
                <svg id="kpi-criticas-spark" data-auditoria-spark data-valor="<?= (int) $alertas_criticas ?>" data-color="#EF4444" class="kpi-sparkline" viewBox="0 0 100 30" preserveAspectRatio="none"></svg>
                <div class="mini-kpi-content">
                    <span class="mini-kpi-value" id="kpi-criticas"><?= number_format($alertas_criticas) ?></span>
                    <span class="mini-kpi-label">Alertas Críticas</span>
                </div>
            </div>
            <div class="kpi-divider"></div>
            <div class="mini-kpi">
                <svg id="kpi-fallidos-spark" data-auditoria-spark data-valor="<?= (int) $intentos_fallidos ?>" data-color="#F59E0B" class="kpi-sparkline" viewBox="0 0 100 30" preserveAspectRatio="none"></svg>
                <div class="mini-kpi-content">
                    <span class="mini-kpi-value" id="kpi-fallidos"><?= number_format($intentos_fallidos) ?></span>
                    <span class="mini-kpi-label">Logins Fallidos</span>
                </div>
            </div>
        </div>

        <div class="header-actions gestion-acciones">
            <button class="btn-secondary" data-ui-accion="exportar-auditoria-excel">
                <i class="fas fa-file-excel gestion-icono-azul"></i> Excel
            </button>
            <button class="btn-secondary" data-ui-accion="exportar-auditoria-pdf">
                <i class="fas fa-file-pdf gestion-icono-azul"></i> PDF
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="auditoria-filtros-caja">
        <form method="GET" action="index.php" class="auditoria-filtros">
            <input type="hidden" name="action" value="admin_auditoria">
            
            <div class="auditoria-filtro">
                <label class="auditoria-filtro-etiqueta">Persona</label>
                <input type="text" name="usuario" value="<?=htmlspecialchars($_GET['usuario']??'')?>" placeholder="Nombre, documento o correo" class="auditoria-campo">
            </div>
            
            <div class="auditoria-filtro">
                <label class="auditoria-filtro-etiqueta">Tipo Acción</label>
                <select name="accion" class="auditoria-select">
                    <option value="">Todas</option>
                    <?php foreach($acciones as $a): ?>
                        <option value="<?=$a?>" <?=($_GET['accion']??'')===$a?'selected':''?>><?=$a?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="auditoria-filtro">
                <label class="auditoria-filtro-etiqueta">Tabla Afectada</label>
                <select name="tabla" class="auditoria-select">
                    <option value="">Todas</option>
                    <?php foreach($tablas as $t): ?>
                        <option value="<?=$t?>" <?=($_GET['tabla']??'')===$t?'selected':''?>><?=$t?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="auditoria-filtro">
                <label class="auditoria-filtro-etiqueta">Desde</label>
                <input type="date" name="fecha_desde" value="<?=htmlspecialchars($_GET['fecha_desde']??'')?>" class="auditoria-campo">
            </div>
            
            <div class="auditoria-filtro">
                <label class="auditoria-filtro-etiqueta">Hasta</label>
                <input type="date" name="fecha_hasta" value="<?=htmlspecialchars($_GET['fecha_hasta']??'')?>" class="auditoria-campo">
            </div>
            
            <button type="submit" class="auditoria-buscar"><i class="fas fa-search"></i> Buscar</button>
            <a href="index.php?action=admin_auditoria" class="auditoria-limpiar"><i class="fas fa-eraser"></i> Limpiar</a>
        </form>
    </div>

    <!-- Tabla Estilizada -->
    <div class="auditoria-tabla-caja">
        <div class="table-responsive auditoria-tabla-scroll">
            <table class="data-table auditoria-tabla" id="tablaAuditoria">
                <thead class="auditoria-tabla-cabecera">
                    <tr>
                        <th class="auditoria-tabla-etiqueta">Fecha/Hora</th>
                        <th class="auditoria-tabla-etiqueta">Usuario</th>
                        <th class="auditoria-tabla-etiqueta">Acción</th>
                        <th class="auditoria-tabla-etiqueta">Tabla</th>
                        <th class="auditoria-tabla-etiqueta">Descripción</th>
                        <th class="auditoria-tabla-etiqueta">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($logs)): ?>
                        <tr><td colspan="6" class="auditoria-vacia"><i class="fas fa-search auditoria-vacia-icono"></i><br>No hay registros de auditoría para estos filtros.</td></tr>
                    <?php else: ?>
                        <?php foreach($logs as $log): 
                            $icon = $accionIcons[$log['accion']] ?? 'fa-circle';
                        ?>
                            <tr class="auditoria-fila">
                                <td class="auditoria-fecha">
                                    <i class="far fa-clock auditoria-reloj"></i>
                                    <?= date('d/m/Y H:i', strtotime($log['fecha_hora'])) ?>
                                </td>
                                <td class="auditoria-persona">
                                    <?= htmlspecialchars($log['usuario_nombre'] ?? 'Sistema') ?>
                                </td>
                                <td class="auditoria-celda">
                                    <span data-accion="<?= htmlspecialchars($log['accion'], ENT_QUOTES, 'UTF-8') ?>" class="auditoria-insignia">
                                        <i class="fas <?= $icon ?>"></i> <?= htmlspecialchars($log['accion'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="auditoria-entidad">
                                    <?= htmlspecialchars($log['tabla_afectada'] ?? '—') ?>
                                </td>
                                <td class="auditoria-descripcion-evento">
                                    <?= htmlspecialchars($log['descripcion'] ?? 'Sin descripción') ?>
                                    <?php if(!empty($log['registro_id'])): ?>
                                        <br><small class="auditoria-gris">ID: <?= htmlspecialchars($log['registro_id']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="auditoria-ip">
                                    <?= htmlspecialchars($log['ip_address'] ?? '—') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginación -->
    <?php if($totalPages > 1): ?>
        <div class="auditoria-paginacion">
            <?php for($i=1; $i<=$totalPages; $i++): ?>
                <a href="index.php?action=admin_auditoria&page=<?=$i?>&usuario=<?=urlencode($_GET['usuario']??'')?>&accion=<?=urlencode($_GET['accion']??'')?>&tabla=<?=urlencode($_GET['tabla']??'')?>&fecha_desde=<?=urlencode($_GET['fecha_desde']??'')?>&fecha_hasta=<?=urlencode($_GET['fecha_hasta']??'')?>"
                   class="auditoria-pagina <?= $page == $i ? 'is-activa' : '' ?>">
                    <?=$i?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>

    <p class="auditoria-total">
        Mostrando <?= count($logs) ?> de <?= $total ?> registros de seguridad
    </p>
</div>

<script src="js/indicadores.js?v=1"></script>
<script src="js/auditoria.js?v=1"></script>
